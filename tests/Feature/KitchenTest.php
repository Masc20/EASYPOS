<?php

namespace Tests\Feature;

use App\Domains\Identity\Models\User;
use App\Domains\Kitchen\Actions\ClaimKitchenTicketAction;
use App\Domains\Kitchen\Actions\RecallKitchenTicketAction;
use App\Domains\Kitchen\Actions\ToggleKitchenItemStatusAction;
use App\Domains\Kitchen\Actions\ToggleKitchenTicketPriorityAction;
use App\Domains\Kitchen\Actions\UpdateKitchenTicketStatusAction;
use App\Domains\Kitchen\Livewire\KitchenDisplay;
use App\Domains\Kitchen\Models\KitchenTicket;
use App\Domains\Kitchen\Models\KitchenTicketItem;
use App\Domains\Menu\Models\Meal;
use App\Domains\PointOfSale\Actions\CreateOrderAction;
use App\Domains\PointOfSale\Actions\ProcessPaymentAction;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class KitchenTest extends TestCase
{
    use RefreshDatabase;

    protected User $cook;
    protected User $cashier;
    protected User $chef;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(MenuSeeder::class);

        $this->cook = User::where('emp_id', 'B01-COK-001')->firstOrFail();
        $this->cashier = User::where('emp_id', 'B01-CSH-001')->firstOrFail();
        $this->chef = User::where('emp_id', 'B01-CHF-001')->firstOrFail();
    }

    protected function createPaidOrderWithKitchenTicket(string $orderType = 'dine_in', string $tableNumber = '5'): KitchenTicket
    {
        $meal = Meal::firstOrFail();

        $createOrderAction = app(CreateOrderAction::class);
        $order = $createOrderAction->execute([
            'cashier_id' => $this->cashier->id,
            'branch_id' => $this->cashier->branch_id ?? 1,
            'order_type' => $orderType,
            'table_number' => $tableNumber,
            'items' => [
                [
                    'meal_id' => $meal->id,
                    'quantity' => 2,
                    'unit_price' => (float) $meal->price,
                    'special_instructions' => 'Extra crispy',
                ],
            ],
        ]);

        $processPaymentAction = app(ProcessPaymentAction::class);
        $processPaymentAction->execute($order, [
            'payment_method' => 'cash',
            'amount_tendered' => (float) $order->total_amount + 50.00,
            'cashier_id' => $this->cashier->id,
        ]);

        return KitchenTicket::where('order_id', $order->id)->firstOrFail();
    }

    public function test_cook_can_view_kitchen_display(): void
    {
        $response = $this->actingAs($this->cook)->get('/kitchen');

        $response->assertStatus(200);
        $response->assertSee('Kitchen Display System (KDS)');
        $response->assertSee('Live Queue');
    }

    public function test_cashier_is_forbidden_from_accessing_kitchen(): void
    {
        $response = $this->actingAs($this->cashier)->get('/kitchen');

        $response->assertStatus(403);
    }

    public function test_incoming_pos_ticket_appears_in_new_orders(): void
    {
        $ticket = $this->createPaidOrderWithKitchenTicket();

        Livewire::actingAs($this->cook)
            ->test(KitchenDisplay::class)
            ->assertSee($ticket->ticket_number)
            ->assertSee('Table 5')
            ->assertSee('Extra crispy')
            ->assertSee('Start Cooking');
    }

    public function test_cook_can_claim_ticket_and_transition_to_in_prep(): void
    {
        $ticket = $this->createPaidOrderWithKitchenTicket();

        Livewire::actingAs($this->cook)
            ->test(KitchenDisplay::class)
            ->call('claimTicket', $ticket->id)
            ->assertSee('Mark Ready');

        $ticket->refresh();
        $this->assertEquals('in_prep', $ticket->status);
        $this->assertEquals($this->cook->id, $ticket->cook_id);
        $this->assertNotNull($ticket->started_at);
        $this->assertEquals('cooking', $ticket->items->first()->status);
    }

    public function test_cook_can_toggle_individual_item_status(): void
    {
        $ticket = $this->createPaidOrderWithKitchenTicket();
        app(ClaimKitchenTicketAction::class)->execute($ticket, $this->cook);

        $item = $ticket->items->first();
        $this->assertEquals('cooking', $item->status);

        Livewire::actingAs($this->cook)
            ->test(KitchenDisplay::class)
            ->call('toggleItem', $item->id);

        $item->refresh();
        $this->assertEquals('done', $item->status);
        $this->assertNotNull($item->completed_at);

        // When all items are done, ticket auto advances to ready
        $ticket->refresh();
        $this->assertEquals('ready', $ticket->status);
    }

    public function test_cook_can_mark_ticket_ready(): void
    {
        $ticket = $this->createPaidOrderWithKitchenTicket();
        app(ClaimKitchenTicketAction::class)->execute($ticket, $this->cook);

        Livewire::actingAs($this->cook)
            ->test(KitchenDisplay::class)
            ->call('markReady', $ticket->id)
            ->assertSee('Bump Ticket');

        $ticket->refresh();
        $this->assertEquals('ready', $ticket->status);
        $this->assertNotNull($ticket->ready_at);
    }

    public function test_cook_or_runner_can_mark_ticket_served_and_completes_order(): void
    {
        $ticket = $this->createPaidOrderWithKitchenTicket();
        app(UpdateKitchenTicketStatusAction::class)->execute($ticket, 'ready');

        Livewire::actingAs($this->cook)
            ->test(KitchenDisplay::class)
            ->call('markServed', $ticket->id);

        $ticket->refresh();
        $this->assertEquals('served', $ticket->status);
        $this->assertNotNull($ticket->served_at);

        // Associated order is completed
        $this->assertEquals('completed', $ticket->order->status);
        $this->assertNotNull($ticket->order->completed_at);
    }

    public function test_cook_can_recall_a_served_ticket(): void
    {
        $ticket = $this->createPaidOrderWithKitchenTicket();
        app(UpdateKitchenTicketStatusAction::class)->execute($ticket, 'served');
        $this->assertEquals('completed', $ticket->order->status);

        Livewire::actingAs($this->cook)
            ->test(KitchenDisplay::class)
            ->call('recallTicket', $ticket->id);

        $ticket->refresh();
        $this->assertEquals('ready', $ticket->status);
        $this->assertNull($ticket->served_at);
        $this->assertEquals('in_kitchen', $ticket->order->status);
    }

    public function test_cook_can_toggle_priority_to_rush(): void
    {
        $ticket = $this->createPaidOrderWithKitchenTicket();
        $this->assertEquals('normal', $ticket->priority);

        Livewire::actingAs($this->cook)
            ->test(KitchenDisplay::class)
            ->call('togglePriority', $ticket->id);

        $ticket->refresh();
        $this->assertEquals('rush', $ticket->priority);

        Livewire::actingAs($this->cook)
            ->test(KitchenDisplay::class)
            ->call('togglePriority', $ticket->id);

        $ticket->refresh();
        $this->assertEquals('normal', $ticket->priority);
    }

    public function test_filters_by_dining_type(): void
    {
        $dineInTicket = $this->createPaidOrderWithKitchenTicket('dine_in', 'Table 10');
        $takeoutTicket = $this->createPaidOrderWithKitchenTicket('takeout');

        Livewire::actingAs($this->cook)
            ->test(KitchenDisplay::class)
            ->set('diningFilter', 'takeout')
            ->assertSee($takeoutTicket->ticket_number)
            ->assertDontSee('Table 10')
            ->set('diningFilter', 'dine_in')
            ->assertSee($dineInTicket->ticket_number)
            ->assertSee('Table 10');
    }
}
