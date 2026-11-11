<?php

namespace Tests\Feature\Performance;

use App\Domains\Identity\Models\User;
use App\Domains\Kitchen\Actions\ClaimKitchenTicketAction;
use App\Domains\Kitchen\Actions\ToggleKitchenItemStatusAction;
use App\Domains\Kitchen\Livewire\KitchenDisplay;
use App\Domains\Kitchen\Models\KitchenTicket;
use App\Domains\Kitchen\Models\KitchenTicketItem;
use App\Domains\Menu\Models\Meal;
use App\Domains\PointOfSale\Models\Order;
use App\Domains\PointOfSale\Models\OrderItem;
use Carbon\Carbon;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class KDSQueueSignatureAndOverflowTest extends TestCase
{
    use RefreshDatabase;

    protected User $cookB1;
    protected User $cookB2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(MenuSeeder::class);

        $this->cookB1 = User::where('emp_id', 'B01-COK-001')->firstOrFail();
        $this->cookB2 = User::where('emp_id', 'B02-COK-001')->firstOrFail();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * Helper to create active orders and kitchen tickets for a branch.
     */
    protected function createTicketsForBranch(int $count, int $branchId, string $status = 'new', ?Carbon $createdAt = null): void
    {
        $meal = Meal::forBranch($branchId)->first() ?? Meal::firstOrFail();
        $time = $createdAt ?? now();

        for ($i = 1; $i <= $count; $i++) {
            $order = Order::create([
                'branch_id' => $branchId,
                'cashier_id' => $this->cookB1->id,
                'order_number' => "ORD-B{$branchId}-" . str_pad((string) $i, 4, '0', STR_PAD_LEFT) . '-' . uniqid(),
                'order_type' => 'dine_in',
                'table_number' => (string) ($i % 20 + 1),
                'customer_name' => "Customer {$i}",
                'total_amount' => 150.00,
                'payment_status' => 'paid',
                'status' => 'completed',
                'created_at' => $time,
                'updated_at' => $time,
            ]);

            $orderItem = OrderItem::create([
                'order_id' => $order->id,
                'meal_id' => $meal->id,
                'meal_name' => $meal->name,
                'unit_price' => 150.00,
                'quantity' => 1,
                'subtotal' => 150.00,
                'created_at' => $time,
                'updated_at' => $time,
            ]);

            $ticket = KitchenTicket::create([
                'branch_id' => $branchId,
                'order_id' => $order->id,
                'ticket_number' => "K-B{$branchId}-" . str_pad((string) $i, 4, '0', STR_PAD_LEFT) . '-' . uniqid(),
                'status' => $status,
                'priority' => $i % 5 === 0 ? 'rush' : 'normal',
                'created_at' => $time,
                'updated_at' => $time,
            ]);

            KitchenTicketItem::create([
                'ticket_id' => $ticket->id,
                'order_item_id' => $orderItem->id,
                'status' => 'pending',
                'created_at' => $time,
                'updated_at' => $time,
            ]);
        }
    }

    /**
     * Scenario 1: Item status toggles propagate updated_at bump to parent ticket and trigger KDS signature version bump.
     */
    public function test_kds_signature_detects_item_status_toggle_on_active_ticket(): void
    {
        Carbon::setTestNow('2026-11-10 12:00:00');

        $meal = Meal::firstOrFail();
        $order = Order::create([
            'branch_id' => 1,
            'cashier_id' => $this->cookB1->id,
            'order_number' => 'ORD-B01-ITEM-TEST',
            'order_type' => 'dine_in',
            'table_number' => '3',
            'total_amount' => 300.00,
            'payment_status' => 'paid',
            'status' => 'completed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $item1 = OrderItem::create([
            'order_id' => $order->id,
            'meal_id' => $meal->id,
            'meal_name' => 'Adobo',
            'unit_price' => 150.00,
            'quantity' => 1,
            'subtotal' => 150.00,
        ]);
        $item2 = OrderItem::create([
            'order_id' => $order->id,
            'meal_id' => $meal->id,
            'meal_name' => 'Sinigang',
            'unit_price' => 150.00,
            'quantity' => 1,
            'subtotal' => 150.00,
        ]);

        $ticket = KitchenTicket::create([
            'branch_id' => 1,
            'order_id' => $order->id,
            'ticket_number' => 'K-B01-0099',
            'status' => 'in_prep',
            'priority' => 'normal',
            'started_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $kItem1 = KitchenTicketItem::create([
            'ticket_id' => $ticket->id,
            'order_item_id' => $item1->id,
            'status' => 'cooking',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $kItem2 = KitchenTicketItem::create([
            'ticket_id' => $ticket->id,
            'order_item_id' => $item2->id,
            'status' => 'cooking',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $testable = Livewire::actingAs($this->cookB1)->test(KitchenDisplay::class);
        $initialVersion = $testable->get('lastTicketVersion');

        // Confirm clean poll skips render
        $testable->call('pollUpdates');
        $this->assertFalse(isset($testable->effects['html']), 'Initial poll without changes should skip render.');

        $ticketUpdatedAtBefore = $ticket->fresh()->updated_at->toDateTimeString();

        // Advance clock by 5 seconds
        Carbon::setTestNow('2026-11-10 12:00:05');

        // Cook toggles item 1 to done (ticket remains 'in_prep' because item 2 is still cooking)
        $action = app(ToggleKitchenItemStatusAction::class);
        $action->execute($kItem1, 'done');

        $kItem1->refresh();
        $this->assertEquals('done', $kItem1->status);

        $ticket->refresh();
        $this->assertEquals('in_prep', $ticket->status, 'Ticket should remain in_prep when only one item completed.');
        $this->assertTrue(
            $ticket->updated_at->gt(Carbon::parse($ticketUpdatedAtBefore)),
            'Item touch propagation must bump parent ticket updated_at timestamp.'
        );

        // KDS polling must now detect the signature change and re-render
        $testable->call('pollUpdates');
        $newVersion = $testable->get('lastTicketVersion');

        $this->assertNotEquals($initialVersion, $newVersion, 'Ticket signature version must change when item is toggled.');
        $this->assertTrue(isset($testable->effects['html']), 'Expected HTML re-render when item status changes on active ticket.');
    }

    /**
     * Scenario 2: Signature query scope detects status transitions on active tickets carried over past midnight.
     */
    public function test_kds_signature_detects_status_change_on_ticket_created_before_midnight(): void
    {
        // 1. Ticket created yesterday at 23:50:00
        Carbon::setTestNow('2026-11-10 23:50:00');

        $meal = Meal::firstOrFail();
        $order = Order::create([
            'branch_id' => 1,
            'cashier_id' => $this->cookB1->id,
            'order_number' => 'ORD-MIDNIGHT-01',
            'order_type' => 'dine_in',
            'table_number' => '12',
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
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $ticket = KitchenTicket::create([
            'branch_id' => 1,
            'order_id' => $order->id,
            'ticket_number' => 'K-B01-MIDNIGHT',
            'status' => 'new',
            'priority' => 'normal',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        KitchenTicketItem::create([
            'ticket_id' => $ticket->id,
            'order_item_id' => $orderItem->id,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2. Roll past midnight to 00:05:00 next day
        Carbon::setTestNow('2026-11-11 00:05:00');

        $testable = Livewire::actingAs($this->cookB1)->test(KitchenDisplay::class);
        $midnightVersion = $testable->get('lastTicketVersion');

        // Confirm poll skips render when unchanged
        $testable->call('pollUpdates');
        $this->assertFalse(isset($testable->effects['html']));

        // 3. At 00:06:00, cook claims the ticket
        Carbon::setTestNow('2026-11-11 00:06:00');
        app(ClaimKitchenTicketAction::class)->execute($ticket, $this->cookB1);

        $ticket->refresh();
        $this->assertEquals('in_prep', $ticket->status);
        $this->assertEquals('2026-11-11 00:06:00', $ticket->updated_at->toDateTimeString());

        // 4. KDS poll updates must detect the cross-midnight ticket update
        $testable->call('pollUpdates');
        $updatedVersion = $testable->get('lastTicketVersion');

        $this->assertNotEquals($midnightVersion, $updatedVersion, 'Signature version must update for cross-midnight tickets.');
        $this->assertTrue(isset($testable->effects['html']), 'KDS must re-render when cross-midnight ticket status transitions.');
    }

    /**
     * Scenario 3: Prominent overflow warning banner appears when active queue exceeds 75 tickets.
     */
    public function test_kds_displays_overflow_warning_badge_when_active_tickets_exceed_75(): void
    {
        Carbon::setTestNow('2026-11-11 18:00:00');

        // Seed 85 active tickets for Branch 1
        $this->createTicketsForBranch(85, 1, 'new');

        $this->assertEquals(85, KitchenTicket::forBranch(1)->active()->count());

        $testable = Livewire::actingAs($this->cookB1)->test(KitchenDisplay::class);

        // Assert viewData passes 85 total active and 10 overflow
        $testable->assertViewHas('totalActiveCount', 85);
        $testable->assertViewHas('overflowCount', 10);

        // Assert rendered ticket collections cap at 75 total
        $newTickets = $testable->viewData('newTickets');
        $inPrepTickets = $testable->viewData('inPrepTickets');
        $readyTickets = $testable->viewData('readyTickets');

        $this->assertEquals(75, $newTickets->count() + $inPrepTickets->count() + $readyTickets->count());

        // Assert prominent overflow warning banner rendered in DOM
        $testable->assertSee('Displaying 75 of 85 active tickets (+10 orders in backlog)');
        $testable->assertSee('Backlog: +10');
        $testable->assertSee('Kitchen Queue Overflow Warning');
    }

    /**
     * Scenario 4: Multi-branch isolation is strictly maintained across signature calculations and active queue.
     */
    public function test_kds_branch_isolation_in_signature_and_queue(): void
    {
        Carbon::setTestNow('2026-11-11 14:00:00');

        // Create 2 tickets for Branch 1 and 2 tickets for Branch 2
        $this->createTicketsForBranch(2, 1, 'new');
        $this->createTicketsForBranch(2, 2, 'new');

        $testableB1 = Livewire::actingAs($this->cookB1)->test(KitchenDisplay::class);
        $sigB1Initial = $testableB1->get('lastTicketVersion');

        $testableB2 = Livewire::actingAs($this->cookB2)->test(KitchenDisplay::class);
        $sigB2Initial = $testableB2->get('lastTicketVersion');

        // Initial signatures for separate branches should be independent
        $this->assertNotEmpty($sigB1Initial);
        $this->assertNotEmpty($sigB2Initial);

        // Advance time and add/modify tickets ONLY for Branch 2
        Carbon::setTestNow('2026-11-11 14:05:00');
        $this->createTicketsForBranch(3, 2, 'new');

        // Branch 2 signature must change
        $componentB2 = $testableB2->instance();
        $sigB2Updated = $componentB2->getTicketSignatureVersion(2);
        $this->assertNotEquals($sigB2Initial, $sigB2Updated);

        // Branch 1 signature must REMAIN UNCHANGED
        $componentB1 = $testableB1->instance();
        $sigB1AfterB2Change = $componentB1->getTicketSignatureVersion(1);
        $this->assertEquals($sigB1Initial, $sigB1AfterB2Change, 'Branch 2 ticket activity must not alter Branch 1 signature.');

        // Switch authentication context back to Branch 1 Cook
        $this->actingAs($this->cookB1);

        // Polling Branch 1 must continue to skip render
        $testableB1->call('pollUpdates');
        $this->assertFalse(isset($testableB1->effects['html']), 'Branch 1 KDS should not re-render due to Branch 2 changes.');

        // Branch 1 queue must never contain Branch 2 ticket numbers
        $branch2Tickets = KitchenTicket::forBranch(2)->pluck('ticket_number')->toArray();
        foreach ($branch2Tickets as $b2TicketNumber) {
            $testableB1->assertDontSee($b2TicketNumber);
        }
    }
}
