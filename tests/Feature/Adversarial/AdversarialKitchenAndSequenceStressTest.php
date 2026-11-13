<?php

namespace Tests\Feature\Adversarial;

use App\Domains\Branches\Models\Branch;
use App\Domains\Identity\Models\User;
use App\Domains\Kitchen\Actions\ToggleKitchenItemStatusAction;
use App\Domains\Kitchen\Livewire\KitchenDisplay;
use App\Domains\Kitchen\Models\KitchenTicket;
use App\Domains\Kitchen\Models\KitchenTicketItem;
use App\Domains\Menu\Models\Meal;
use App\Domains\Menu\Models\MealCategory;
use App\Domains\PointOfSale\Models\BranchDailySequence;
use App\Domains\PointOfSale\Models\Order;
use App\Domains\PointOfSale\Models\OrderItem;
use App\Support\Enums\KitchenTicketStatus;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdversarialKitchenAndSequenceStressTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branchA;
    protected Branch $branchB;
    protected User $cookA;
    protected User $cashierA;
    protected Meal $mealA;
    protected KitchenDisplay $kitchenDisplay;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->branchA = Branch::firstOrCreate(
            ['code' => 'B01'],
            ['name' => 'Main Branch', 'is_active' => true]
        );

        $this->branchB = Branch::firstOrCreate(
            ['code' => 'B02'],
            ['name' => 'Second Branch', 'is_active' => true]
        );

        $this->cookA = User::where('emp_id', 'B01-COK-001')->firstOrFail();
        $this->cashierA = User::where('emp_id', 'B01-CSH-001')->firstOrFail();

        $category = MealCategory::firstOrCreate(
            ['slug' => 'rice-meals'],
            ['name' => 'Rice Meals', 'is_active' => true]
        );

        $this->mealA = Meal::create([
            'category_id' => $category->id,
            'branch_id' => $this->branchA->id,
            'name' => 'Adversarial Test Bowl',
            'menu_code' => 'ATB-001',
            'price' => 120.00,
            'is_available' => true,
            'track_stock' => false,
        ]);

        $this->kitchenDisplay = new KitchenDisplay();
    }

    /**
     * Helper to create a ticket with associated order and item.
     */
    protected function createTicketWithItem(
        Branch $branch,
        string $status = 'new',
        ?Carbon $servedAt = null,
        ?Carbon $createdAt = null
    ): KitchenTicket {
        $timestamp = $createdAt ?? now();

        $order = Order::create([
            'order_number' => 'ORD-' . Str::upper(Str::random(10)),
            'branch_id' => $branch->id,
            'cashier_id' => $this->cashierA->id,
            'order_type' => 'dine_in',
            'table_number' => 'T1',
            'subtotal' => 120.00,
            'discount_amount' => 0.00,
            'tax_amount' => 0.00,
            'total_amount' => 120.00,
            'status' => 'pending',
            'payment_status' => 'paid',
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        $orderItem = OrderItem::create([
            'order_id' => $order->id,
            'meal_id' => $this->mealA->id,
            'meal_name' => $this->mealA->name,
            'quantity' => 1,
            'unit_price' => 120.00,
            'subtotal' => 120.00,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        $ticket = KitchenTicket::create([
            'ticket_number' => 'K-' . Str::upper(Str::random(6)),
            'order_id' => $order->id,
            'branch_id' => $branch->id,
            'status' => $status,
            'priority' => 'normal',
            'served_at' => $servedAt,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        KitchenTicketItem::create([
            'ticket_id' => $ticket->id,
            'order_item_id' => $orderItem->id,
            'status' => 'pending',
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        return $ticket;
    }

    /**
     * 1. Stress-test Kitchen Polling: Historical tickets served yesterday or older
     * are NEVER included in getTicketSignatureVersion().
     */
    public function test_historical_tickets_served_yesterday_or_older_are_never_included_in_signature(): void
    {
        Carbon::setTestNow('2026-11-13 15:00:00');

        $emptySignature = $this->kitchenDisplay->getTicketSignatureVersion($this->branchA->id);
        $this->assertIsString($emptySignature);

        // Create 5 historical tickets served at various past intervals
        $this->createTicketWithItem($this->branchA, 'served', Carbon::parse('2026-11-12 23:59:59'), Carbon::parse('2026-11-12 23:00:00'));
        $this->createTicketWithItem($this->branchA, 'served', Carbon::parse('2026-11-12 12:00:00'), Carbon::parse('2026-11-12 11:00:00'));
        $this->createTicketWithItem($this->branchA, 'served', Carbon::parse('2026-11-11 18:00:00'), Carbon::parse('2026-11-11 17:00:00'));
        $this->createTicketWithItem($this->branchA, 'served', Carbon::parse('2026-10-13 10:00:00'), Carbon::parse('2026-10-13 09:00:00'));
        $this->createTicketWithItem($this->branchA, 'served', Carbon::parse('2026-01-01 08:00:00'), Carbon::parse('2026-01-01 07:00:00'));

        // All 5 tickets are served before today's startOfDay ('2026-11-13 00:00:00')
        $historicalSignature = $this->kitchenDisplay->getTicketSignatureVersion($this->branchA->id);
        $this->assertSame(
            $emptySignature,
            $historicalSignature,
            'Historical tickets served yesterday or older must never alter the active polling signature version.'
        );

        // Cancelled tickets from today must also be excluded
        $this->createTicketWithItem($this->branchA, KitchenTicketStatus::CANCELLED->value, null, Carbon::parse('2026-11-13 14:00:00'));
        $cancelledSignature = $this->kitchenDisplay->getTicketSignatureVersion($this->branchA->id);
        $this->assertSame(
            $emptySignature,
            $cancelledSignature,
            'Cancelled tickets must be excluded from the signature version.'
        );

        // Active ticket (served_at is NULL) created 3 days ago MUST be included
        $activeOldTicket = $this->createTicketWithItem($this->branchA, 'new', null, Carbon::parse('2026-11-10 10:00:00'));
        $activeSignature = $this->kitchenDisplay->getTicketSignatureVersion($this->branchA->id);
        $this->assertNotSame(
            $emptySignature,
            $activeSignature,
            'Active tickets with served_at = NULL must always be included regardless of age.'
        );

        Carbon::setTestNow();
    }

    /**
     * 2. Stress-test Kitchen Polling: Tickets served today are included until midnight rollover.
     */
    public function test_tickets_served_today_are_included_in_signature_until_midnight_rollover(): void
    {
        Carbon::setTestNow('2026-11-13 12:00:00');

        $emptySignature = $this->kitchenDisplay->getTicketSignatureVersion($this->branchA->id);

        // Serve a ticket today at 12:05 PM
        $ticketToday = $this->createTicketWithItem(
            $this->branchA,
            'served',
            Carbon::parse('2026-11-13 12:05:00'),
            Carbon::parse('2026-11-13 11:55:00')
        );

        $todaySignature = $this->kitchenDisplay->getTicketSignatureVersion($this->branchA->id);
        $this->assertNotSame(
            $emptySignature,
            $todaySignature,
            'Tickets served today must be included in the kitchen signature version.'
        );

        // Fast-forward to 23:59:59 tonight: ticket served today must STILL be included
        Carbon::setTestNow('2026-11-13 23:59:59');
        $preMidnightSignature = $this->kitchenDisplay->getTicketSignatureVersion($this->branchA->id);
        $this->assertSame(
            $todaySignature,
            $preMidnightSignature,
            'Ticket served today must remain in the signature right up to midnight.'
        );

        // Step 2 seconds past midnight into 2026-11-14: ticket is now from yesterday!
        Carbon::setTestNow('2026-11-14 00:00:01');
        $postMidnightSignature = $this->kitchenDisplay->getTicketSignatureVersion($this->branchA->id);

        $this->assertSame(
            $emptySignature,
            $postMidnightSignature,
            'Ticket served yesterday must automatically fall out of signature after midnight rollover.'
        );

        Carbon::setTestNow();
    }

    /**
     * 3. Stress-test Item Status Toggle: Updating item touches parent ticket via model touch
     * and mutates the signature without redundant SQL writes.
     */
    public function test_updating_item_status_touches_ticket_and_mutates_signature_without_redundant_writes(): void
    {
        Carbon::setTestNow('2026-11-13 10:00:00');

        $ticket = $this->createTicketWithItem($this->branchA, 'in_prep');

        // Add a second pending item so toggling item 1 keeps ticket in 'in_prep' and isolates the touch write
        $secondOrderItem = OrderItem::create([
            'order_id' => $ticket->order_id,
            'meal_id' => $this->mealA->id,
            'meal_name' => $this->mealA->name,
            'quantity' => 1,
            'unit_price' => 120.00,
            'subtotal' => 120.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        KitchenTicketItem::create([
            'ticket_id' => $ticket->id,
            'order_item_id' => $secondOrderItem->id,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $item = $ticket->items()->firstOrFail();

        // Baseline signature
        $initialSignature = $this->kitchenDisplay->getTicketSignatureVersion($this->branchA->id);
        $initialTicketUpdatedAt = $ticket->fresh()->updated_at;

        // Step forward 5 minutes so updated_at is distinctly newer
        Carbon::setTestNow('2026-11-13 10:05:00');

        DB::enableQueryLog();
        DB::flushQueryLog();

        $action = new ToggleKitchenItemStatusAction();
        $this->actingAs($this->cookA);
        $action->execute($item);

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // Verify query count on kitchen_tickets: exactly ONE update query (from Eloquent $touches),
        // NOT a second redundant $ticket?->touch() call
        $ticketUpdateQueries = array_filter($queries, function ($q) {
            $sql = strtolower($q['query']);
            return str_contains($sql, 'update') && str_contains($sql, 'kitchen_tickets');
        });

        $this->assertCount(
            1,
            $ticketUpdateQueries,
            'There must be exactly 1 UPDATE query to kitchen_tickets (via model $touches), with no redundant touch.'
        );

        // Verify parent ticket updated_at was bumped
        $ticket->refresh();
        $this->assertTrue(
            $ticket->updated_at->gt($initialTicketUpdatedAt),
            'Parent kitchen ticket updated_at must advance when an item status is toggled.'
        );

        // Verify kitchen polling signature has changed
        $updatedSignature = $this->kitchenDisplay->getTicketSignatureVersion($this->branchA->id);
        $this->assertNotSame(
            $initialSignature,
            $updatedSignature,
            'Signature version must mutate when ticket updated_at changes via model touch.'
        );

        Carbon::setTestNow();
    }

    /**
     * 4. Stress-test Daily Sequence Allocation: Rapid allocations are monotonically sequential
     * with 0 duplicate sequence numbers and 0 gaps.
     */
    public function test_daily_sequence_rapid_allocations_are_strictly_monotonic_and_gapless(): void
    {
        $branchId = $this->branchA->id;
        $date = '2026-11-13';

        $allocatedSequences = [];
        $totalAllocations = 50;

        for ($i = 1; $i <= $totalAllocations; $i++) {
            $seq = BranchDailySequence::getNextSequence($branchId, 'order', $date);
            $allocatedSequences[] = $seq;
        }

        // Verify sequential monotonicity
        $this->assertCount($totalAllocations, $allocatedSequences);
        $this->assertSame(
            range(1, $totalAllocations),
            $allocatedSequences,
            'Sequence allocation must produce strictly monotonic sequences (1, 2, 3...) with 0 gaps.'
        );

        // Verify 0 duplicates
        $this->assertSame(
            $allocatedSequences,
            array_unique($allocatedSequences),
            'Sequence allocation must have 0 duplicate numbers.'
        );

        // Verify continuity across pre-existing order records
        $preExistingDate = '2026-11-15';
        for ($k = 1; $k <= 5; $k++) {
            Order::create([
                'order_number' => "B01-20261115-000{$k}",
                'branch_id' => $branchId,
                'cashier_id' => $this->cashierA->id,
                'order_type' => 'dine_in',
                'subtotal' => 100.00,
                'discount_amount' => 0.00,
                'tax_amount' => 0.00,
                'total_amount' => 100.00,
                'status' => 'pending',
                'payment_status' => 'paid',
                'created_at' => Carbon::parse('2026-11-15 08:00:00'),
                'updated_at' => Carbon::parse('2026-11-15 08:00:00'),
            ]);
        }

        // Next sequence should recognize the 5 pre-existing orders and allocate sequence 6
        $firstAfterPreExisting = BranchDailySequence::getNextSequence($branchId, 'order', $preExistingDate);
        $this->assertSame(6, $firstAfterPreExisting, 'Sequence allocation must seamlessly continue after pre-existing count.');

        $secondAfterPreExisting = BranchDailySequence::getNextSequence($branchId, 'order', $preExistingDate);
        $this->assertSame(7, $secondAfterPreExisting, 'Subsequent sequence must increment monotonically to 7.');
    }

    /**
     * 5. Stress-test Sargability and Fresh Query Elimination:
     * Sequence queries use sargable sequence_date = ? without date() wrapper,
     * and execute zero redundant fresh() SELECT queries.
     */
    public function test_sequence_queries_are_sargable_and_execute_zero_redundant_fresh_selects(): void
    {
        $branchId = $this->branchA->id;
        $date = '2026-11-20';

        // Initial allocation (creates the daily sequence row)
        DB::enableQueryLog();
        DB::flushQueryLog();

        $seq1 = BranchDailySequence::getNextSequence($branchId, 'order', $date);
        $this->assertSame(1, $seq1);

        $initialQueries = DB::getQueryLog();
        DB::flushQueryLog();

        // Check for sargability on all queries
        foreach ($initialQueries as $q) {
            $sql = strtolower($q['query']);
            $this->assertStringNotContainsString(
                'date(sequence_date)',
                $sql,
                'Initial sequence query must not wrap sequence_date in date() scalar function.'
            );
        }

        // Subsequent allocation on existing sequence row
        $seq2 = BranchDailySequence::getNextSequence($branchId, 'order', $date);
        $this->assertSame(2, $seq2);

        $subsequentQueries = DB::getQueryLog();
        DB::disableQueryLog();

        // Subsequent allocation should only do lockForUpdate SELECT and increment UPDATE
        $this->assertCount(
            2,
            $subsequentQueries,
            'Subsequent sequence allocation must execute exactly 2 queries (1 lockForUpdate SELECT + 1 increment UPDATE).'
        );

        foreach ($subsequentQueries as $q) {
            $sql = strtolower($q['query']);
            $this->assertStringNotContainsString(
                'date(sequence_date)',
                $sql,
                'Subsequent query must be sargable without date() wrapper.'
            );
            $this->assertStringNotContainsString(
                'select * from `branch_daily_sequences` where `id` =',
                $sql,
                'Redundant fresh() SELECT query must not be executed after increment.'
            );
        }
    }

    /**
     * 6. Stress-test Multi-Branch Isolation: Sequences and kitchen signatures
     * are strictly isolated between branches.
     */
    public function test_multi_branch_isolation_for_sequences_and_kitchen_signatures(): void
    {
        $date = '2026-11-25';

        // 1. Sequence Isolation
        // Allocate 10 in Branch A
        for ($i = 1; $i <= 10; $i++) {
            BranchDailySequence::getNextSequence($this->branchA->id, 'order', $date);
        }

        // Allocate in Branch B -> must start at 1
        $seqB1 = BranchDailySequence::getNextSequence($this->branchB->id, 'order', $date);
        $this->assertSame(1, $seqB1, 'Branch B sequence must start at 1 regardless of Branch A count.');

        // Allocate kitchen_ticket in Branch A -> must start at 1 (type isolation)
        $seqTicketA1 = BranchDailySequence::getNextSequence($this->branchA->id, 'kitchen_ticket', $date);
        $this->assertSame(1, $seqTicketA1, 'Kitchen ticket sequence must start at 1 independent of order sequence.');

        // 2. Kitchen Signature Isolation
        Carbon::setTestNow('2026-11-25 10:00:00');

        $initialSigA = $this->kitchenDisplay->getTicketSignatureVersion($this->branchA->id);
        $initialSigB = $this->kitchenDisplay->getTicketSignatureVersion($this->branchB->id);

        // Add active ticket to Branch A
        $ticketA = $this->createTicketWithItem($this->branchA, 'new', null, Carbon::parse('2026-11-25 10:01:00'));

        $sigA_afterTicketA = $this->kitchenDisplay->getTicketSignatureVersion($this->branchA->id);
        $sigB_afterTicketA = $this->kitchenDisplay->getTicketSignatureVersion($this->branchB->id);

        $this->assertNotSame($initialSigA, $sigA_afterTicketA, 'Branch A signature must update when Branch A ticket arrives.');
        $this->assertSame($initialSigB, $sigB_afterTicketA, 'Branch B signature must NOT change when Branch A ticket arrives.');

        // Add active ticket to Branch B
        $ticketB = $this->createTicketWithItem($this->branchB, 'new', null, Carbon::parse('2026-11-25 10:02:00'));

        $sigA_afterTicketB = $this->kitchenDisplay->getTicketSignatureVersion($this->branchA->id);
        $sigB_afterTicketB = $this->kitchenDisplay->getTicketSignatureVersion($this->branchB->id);

        $this->assertSame($sigA_afterTicketA, $sigA_afterTicketB, 'Branch A signature must NOT change when Branch B ticket arrives.');
        $this->assertNotSame($sigB_afterTicketA, $sigB_afterTicketB, 'Branch B signature must update when Branch B ticket arrives.');

        Carbon::setTestNow();
    }
}
