<?php

namespace Tests\Feature\Performance;

use App\Domains\Identity\Models\User;
use App\Domains\Kitchen\Models\KitchenTicket;
use App\Domains\Kitchen\Models\KitchenTicketItem;
use App\Domains\Menu\Models\Meal;
use App\Domains\PointOfSale\Livewire\OrderProgress;
use App\Domains\PointOfSale\Models\Order;
use App\Domains\PointOfSale\Models\OrderItem;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class OrderProgressDeferredLoadingTest extends TestCase
{
    use RefreshDatabase;

    protected User $cashierB1;
    protected User $cashierB2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(MenuSeeder::class);

        $this->cashierB1 = User::where('emp_id', 'B01-CSH-001')->firstOrFail();
        $this->cashierB2 = User::where('emp_id', 'B02-CSH-001')->firstOrFail();
    }

    /**
     * Helper to create active orders and kitchen tickets for a branch.
     */
    protected function createTicketsForBranch(int $count, int $branchId, string $status = 'new'): void
    {
        $meal = Meal::forBranch($branchId)->first() ?? Meal::firstOrFail();

        for ($i = 1; $i <= $count; $i++) {
            $order = Order::create([
                'branch_id' => $branchId,
                'cashier_id' => $this->cashierB1->id,
                'order_number' => "ORD-B{$branchId}-" . str_pad((string) $i, 4, '0', STR_PAD_LEFT) . '-' . uniqid(),
                'order_type' => 'dine_in',
                'table_number' => (string) ($i % 20 + 1),
                'customer_name' => "Customer {$i}",
                'total_amount' => 150.00,
                'payment_status' => 'paid',
                'status' => 'completed',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $orderItem = OrderItem::create([
                'order_id' => $order->id,
                'meal_id' => $meal->id,
                'meal_name' => $meal->name,
                'unit_price' => 150.00,
                'quantity' => 1,
                'subtotal' => 150.00,
            ]);

            $ticket = KitchenTicket::create([
                'branch_id' => $branchId,
                'order_id' => $order->id,
                'ticket_number' => "K-B{$branchId}-" . str_pad((string) $i, 4, '0', STR_PAD_LEFT) . '-' . uniqid(),
                'status' => $status,
                'priority' => $i % 5 === 0 ? 'rush' : 'normal',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            KitchenTicketItem::create([
                'ticket_id' => $ticket->id,
                'order_item_id' => $orderItem->id,
                'status' => 'pending',
            ]);
        }
    }

    public function test_order_progress_skips_ticket_collection_queries_when_closed(): void
    {
        // 1. Seed 25 active tickets with order items for Branch 1
        $this->createTicketsForBranch(25, $this->cashierB1->branch_id, 'new');

        DB::flushQueryLog();
        DB::enableQueryLog();

        // 2. Mount OrderProgress with default closed state ($isOpen = false)
        $component = Livewire::actingAs($this->cashierB1)
            ->test(OrderProgress::class);

        // 3. Assert component is closed and tickets collection is empty
        $component->assertSet('isOpen', false);
        $tickets = $component->viewData('tickets');
        $this->assertCount(0, $tickets, 'Tickets collection must be empty when drawer is closed.');

        // 4. Assert badge counts accurately reflect the 25 tickets
        $counts = $component->get('counts');
        $this->assertEquals(25, $counts['new']);
        $this->assertEquals(25, $counts['all']);

        // 5. Verify no queries to order_items or kitchen_ticket_items were executed
        $queries = DB::getQueryLog();
        $executedTables = collect($queries)->pluck('query')->join(' ');

        $this->assertStringNotContainsString('order_items', $executedTables, 'Must not query order_items when closed.');
        $this->assertStringNotContainsString('kitchen_ticket_items', $executedTables, 'Must not query kitchen_ticket_items when closed.');

        // Only 1 grouped count query should be executed on kitchen_tickets
        $ticketQueries = collect($queries)->filter(fn ($q) => str_contains($q['query'], 'kitchen_tickets'));
        $this->assertCount(1, $ticketQueries, 'Only 1 badge count aggregate query should run on kitchen_tickets.');
    }

    public function test_order_progress_hydrates_tickets_upon_open_event(): void
    {
        // 1. Seed 25 active tickets with order items for Branch 1
        $this->createTicketsForBranch(25, $this->cashierB1->branch_id, 'new');

        // 2. Mount component closed, then trigger open event
        $component = Livewire::actingAs($this->cashierB1)
            ->test(OrderProgress::class)
            ->assertSet('isOpen', false)
            ->dispatch('open-order-progress')
            ->assertSet('isOpen', true);

        // 3. Assert tickets collection is now hydrated with 25 records
        $tickets = $component->viewData('tickets');
        $this->assertCount(25, $tickets, 'Tickets collection must hydrate all 25 tickets upon opening.');

        // 4. Verify relations are eager-loaded on hydrated tickets
        $firstTicket = $tickets->first();
        $this->assertNotNull($firstTicket);
        $this->assertTrue($firstTicket->relationLoaded('order'), 'Order relation must be eager loaded.');
        $this->assertTrue($firstTicket->relationLoaded('items'), 'Items relation must be eager loaded.');
        $this->assertTrue($firstTicket->relationLoaded('cook'), 'Cook relation must be eager loaded.');
        $this->assertTrue($firstTicket->relationLoaded('branch'), 'Branch relation must be eager loaded.');
        $this->assertTrue($firstTicket->order->relationLoaded('items'), 'Nested order.items must be eager loaded.');
        $this->assertTrue($firstTicket->items->first()->relationLoaded('orderItem'), 'Nested items.orderItem must be eager loaded.');
    }

    public function test_order_progress_preserves_branch_tenancy_in_counts_and_tickets(): void
    {
        // Seed 5 tickets for Branch 1 and 10 tickets for Branch 2
        $this->createTicketsForBranch(5, $this->cashierB1->branch_id, 'new');
        $this->createTicketsForBranch(10, $this->cashierB2->branch_id, 'new');

        // Branch 1 cashier view
        $b1Component = Livewire::actingAs($this->cashierB1)
            ->test(OrderProgress::class)
            ->assertSet('isOpen', false);

        $this->assertEquals(5, $b1Component->get('counts')['all'], 'Branch 1 must only count 5 tickets.');

        $b1Component->call('open');
        $b1Tickets = $b1Component->viewData('tickets');
        $this->assertCount(5, $b1Tickets);
        foreach ($b1Tickets as $ticket) {
            $this->assertEquals($this->cashierB1->branch_id, $ticket->branch_id, 'All tickets must belong to Branch 1.');
        }

        // Branch 2 cashier view
        $b2Component = Livewire::actingAs($this->cashierB2)
            ->test(OrderProgress::class)
            ->assertSet('isOpen', false);

        $this->assertEquals(10, $b2Component->get('counts')['all'], 'Branch 2 must only count 10 tickets.');

        $b2Component->call('open');
        $b2Tickets = $b2Component->viewData('tickets');
        $this->assertCount(10, $b2Tickets);
        foreach ($b2Tickets as $ticket) {
            $this->assertEquals($this->cashierB2->branch_id, $ticket->branch_id, 'All tickets must belong to Branch 2.');
        }
    }

    public function test_order_progress_refresh_tickets_updates_badge_counts_without_fetching_tickets_when_closed(): void
    {
        // 1. Initial 5 tickets
        $this->createTicketsForBranch(5, $this->cashierB1->branch_id, 'new');

        $component = Livewire::actingAs($this->cashierB1)
            ->test(OrderProgress::class)
            ->assertSet('isOpen', false);

        $this->assertEquals(5, $component->get('counts')['all']);
        $this->assertCount(0, $component->viewData('tickets'));

        // 2. Another 3 tickets arrive from floor
        $this->createTicketsForBranch(3, $this->cashierB1->branch_id, 'new');

        // 3. Dispatch kitchen lifecycle event
        DB::flushQueryLog();
        DB::enableQueryLog();

        $component->dispatch('kitchen-ticket-status-updated');

        // 4. Counts are refreshed to 8, but tickets remain deferred (empty)
        $this->assertEquals(8, $component->get('counts')['all']);
        $this->assertCount(0, $component->viewData('tickets'));

        // 5. Verify no ticket models or relations were hydrated
        $queries = DB::getQueryLog();
        $executedTables = collect($queries)->pluck('query')->join(' ');
        $this->assertStringNotContainsString('order_items', $executedTables);
    }
}
