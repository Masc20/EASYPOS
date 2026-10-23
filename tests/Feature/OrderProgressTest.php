<?php

namespace Tests\Feature;

use App\Domains\Branches\Models\Branch;
use App\Domains\Identity\Models\User;
use App\Domains\Kitchen\Actions\ClaimKitchenTicketAction;
use App\Domains\Kitchen\Actions\RecallKitchenTicketAction;
use App\Domains\Kitchen\Actions\ToggleKitchenItemStatusAction;
use App\Domains\Kitchen\Actions\ToggleKitchenTicketPriorityAction;
use App\Domains\Kitchen\Actions\UpdateKitchenTicketStatusAction;
use App\Domains\Kitchen\Models\KitchenTicket;
use App\Domains\Menu\Models\Meal;
use App\Domains\PointOfSale\Actions\CreateOrderAction;
use App\Domains\PointOfSale\Actions\ProcessPaymentAction;
use App\Domains\PointOfSale\Livewire\OrderProgress;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class OrderProgressTest extends TestCase
{
    use RefreshDatabase;

    protected User $cashier;
    protected User $cook;
    protected User $cashierCebu;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(MenuSeeder::class);

        $this->cashier = User::where('emp_id', 'B01-CSH-001')->firstOrFail();
        $this->cook = User::where('emp_id', 'B01-COK-001')->firstOrFail();
        $this->cashierCebu = User::where('emp_id', 'B02-CSH-001')->firstOrFail();
    }

    protected function createPaidTicket(User $cashier, string $table = '5', string $customer = 'Alice'): KitchenTicket
    {
        $meal = Meal::firstOrFail();

        $order = app(CreateOrderAction::class)->execute([
            'cashier_id' => $cashier->id,
            'branch_id' => $cashier->branch_id ?? 1,
            'order_type' => 'dine_in',
            'table_number' => $table,
            'customer_name' => $customer,
            'items' => [
                [
                    'meal_id' => $meal->id,
                    'quantity' => 2,
                    'unit_price' => (float) $meal->price,
                    'special_instructions' => 'Extra sauce',
                ],
            ],
        ]);

        app(ProcessPaymentAction::class)->execute($order, [
            'payment_method' => 'cash',
            'amount_tendered' => (float) $order->total_amount + 50.00,
            'cashier_id' => $cashier->id,
        ]);

        return KitchenTicket::where('order_id', $order->id)->firstOrFail();
    }

    public function test_pos_terminal_header_includes_order_progress_button(): void
    {
        $response = $this->actingAs($this->cashier)->get('/pos');

        $response->assertStatus(200);
        $response->assertSee('Order Progress');
    }

    public function test_cashier_can_open_and_view_order_progress_component(): void
    {
        $ticket = $this->createPaidTicket($this->cashier, '12', 'John Doe');

        Livewire::actingAs($this->cashier)
            ->test(OrderProgress::class)
            ->assertSet('isOpen', false)
            ->dispatch('open-order-progress')
            ->assertSet('isOpen', true)
            ->assertSee($ticket->ticket_number)
            ->assertSee('Table 12')
            ->assertSee('John Doe')
            ->assertSee('Extra sauce')
            ->assertSee('New / Queued')
            ->assertSee('Read-only • Floor Terminal')
            ->call('close')
            ->assertSet('isOpen', false);
    }

    public function test_order_progress_reflects_lifecycle_status_changes(): void
    {
        $ticket = $this->createPaidTicket($this->cashier, '8', 'Bob');

        $component = Livewire::actingAs($this->cashier)->test(OrderProgress::class);
        $component->call('open');
        $component->assertSee('New / Queued');

        // Transition: Cook claims ticket -> in_prep
        $this->actingAs($this->cook);
        app(ClaimKitchenTicketAction::class)->execute($ticket, $this->cook);
        Livewire::actingAs($this->cashier);
        $component->call('refreshTickets');
        $component->assertSee('Cooking (In Prep)');

        // Transition: Mark ready
        $this->actingAs($this->cook);
        app(UpdateKitchenTicketStatusAction::class)->execute($ticket, 'ready');
        Livewire::actingAs($this->cashier);
        $component->call('refreshTickets');
        $component->assertSee('Ready for Pickup');

        // Transition: Mark served
        $this->actingAs($this->cook);
        app(UpdateKitchenTicketStatusAction::class)->execute($ticket, 'served');
        Livewire::actingAs($this->cashier);
        $component->call('refreshTickets');
        $component->assertSee('Served');
    }

    public function test_order_progress_filters_by_status(): void
    {
        $ticketNew = $this->createPaidTicket($this->cashier, '1');
        $ticketInPrep = $this->createPaidTicket($this->cashier, '2');
        app(ClaimKitchenTicketAction::class)->execute($ticketInPrep, $this->cook);

        $ticketReady = $this->createPaidTicket($this->cashier, '3');
        app(UpdateKitchenTicketStatusAction::class)->execute($ticketReady, 'ready');

        Livewire::actingAs($this->cashier)
            ->test(OrderProgress::class)
            ->call('open')
            ->assertSee($ticketNew->ticket_number)
            ->assertSee($ticketInPrep->ticket_number)
            ->assertSee($ticketReady->ticket_number)
            ->call('setStatusFilter', 'ready')
            ->assertSee($ticketReady->ticket_number)
            ->assertDontSee($ticketNew->ticket_number)
            ->assertDontSee($ticketInPrep->ticket_number)
            ->call('setStatusFilter', 'new')
            ->assertSee($ticketNew->ticket_number)
            ->assertDontSee($ticketReady->ticket_number);
    }

    public function test_order_progress_searches_by_table_or_customer(): void
    {
        $ticket1 = $this->createPaidTicket($this->cashier, '42', 'Special VIP');
        $ticket2 = $this->createPaidTicket($this->cashier, '99', 'Walk In');

        Livewire::actingAs($this->cashier)
            ->test(OrderProgress::class)
            ->call('open')
            ->set('search', 'Table 42')
            ->assertSee('Special VIP')
            ->assertDontSee('Walk In')
            ->set('search', 'NonExistent999')
            ->assertSee('No orders matching filter');
    }

    public function test_order_progress_enforces_multi_branch_isolation(): void
    {
        $manilaTicket = $this->createPaidTicket($this->cashier, '10', 'Manila Guest');
        $cebuTicket = $this->createPaidTicket($this->cashierCebu, '20', 'Cebu Guest');

        // Manila Cashier should only see Manila ticket
        Livewire::actingAs($this->cashier)
            ->test(OrderProgress::class)
            ->call('open')
            ->assertSee($manilaTicket->order->order_number)
            ->assertSee('Manila Guest')
            ->assertDontSee($cebuTicket->order->order_number)
            ->assertDontSee('Cebu Guest');

        // Cebu Cashier should only see Cebu ticket
        Livewire::actingAs($this->cashierCebu)
            ->test(OrderProgress::class)
            ->call('open')
            ->assertSee($cebuTicket->order->order_number)
            ->assertSee('Cebu Guest')
            ->assertDontSee($manilaTicket->order->order_number)
            ->assertDontSee('Manila Guest');
    }

    public function test_cashier_is_forbidden_from_claiming_kitchen_ticket(): void
    {
        $ticket = $this->createPaidTicket($this->cashier);

        $this->actingAs($this->cashier);

        try {
            app(ClaimKitchenTicketAction::class)->execute($ticket, $this->cashier);
            $this->fail('Expected 403 HttpException was not thrown.');
        } catch (HttpException $e) {
            $this->assertEquals(403, $e->getStatusCode());
            $this->assertStringContainsString('Unauthorized', $e->getMessage());
        }
    }

    public function test_cashier_is_forbidden_from_updating_kitchen_ticket_status(): void
    {
        $ticket = $this->createPaidTicket($this->cashier);

        $this->actingAs($this->cashier);

        try {
            app(UpdateKitchenTicketStatusAction::class)->execute($ticket, 'ready');
            $this->fail('Expected 403 HttpException was not thrown.');
        } catch (HttpException $e) {
            $this->assertEquals(403, $e->getStatusCode());
            $this->assertStringContainsString('Unauthorized', $e->getMessage());
        }
    }

    public function test_cashier_is_forbidden_from_toggling_kitchen_item_status(): void
    {
        $ticket = $this->createPaidTicket($this->cashier);
        $item = $ticket->items->firstOrFail();

        $this->actingAs($this->cashier);

        try {
            app(ToggleKitchenItemStatusAction::class)->execute($item);
            $this->fail('Expected 403 HttpException was not thrown.');
        } catch (HttpException $e) {
            $this->assertEquals(403, $e->getStatusCode());
            $this->assertStringContainsString('Unauthorized', $e->getMessage());
        }
    }

    public function test_cashier_is_forbidden_from_recalling_kitchen_ticket(): void
    {
        $ticket = $this->createPaidTicket($this->cashier);

        $this->actingAs($this->cashier);

        try {
            app(RecallKitchenTicketAction::class)->execute($ticket);
            $this->fail('Expected 403 HttpException was not thrown.');
        } catch (HttpException $e) {
            $this->assertEquals(403, $e->getStatusCode());
            $this->assertStringContainsString('Unauthorized', $e->getMessage());
        }
    }

    public function test_cashier_is_forbidden_from_toggling_ticket_priority(): void
    {
        $ticket = $this->createPaidTicket($this->cashier);

        $this->actingAs($this->cashier);

        try {
            app(ToggleKitchenTicketPriorityAction::class)->execute($ticket);
            $this->fail('Expected 403 HttpException was not thrown.');
        } catch (HttpException $e) {
            $this->assertEquals(403, $e->getStatusCode());
            $this->assertStringContainsString('Unauthorized', $e->getMessage());
        }
    }
}
