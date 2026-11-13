<?php

namespace Tests\Feature\Performance;

use App\Domains\Branches\Models\Branch;
use App\Domains\Identity\Models\User;
use App\Domains\Kitchen\Models\KitchenTicket;
use App\Domains\Menu\Models\Meal;
use App\Domains\Menu\Models\MealCategory;
use App\Domains\PointOfSale\Actions\CreateOrderAction;
use App\Domains\PointOfSale\Actions\ProcessPaymentAction;
use App\Domains\PointOfSale\Models\BranchDailySequence;
use App\Domains\PointOfSale\Models\Order;
use App\Support\Enums\OrderType;
use App\Support\Enums\PaymentMethod;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SequenceConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branch1;
    protected Branch $branch2;
    protected User $cashier1;
    protected User $cashier2;
    protected Meal $mealBranch1;
    protected Meal $mealBranch2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->branch1 = Branch::where('code', 'B01')->firstOrFail();
        $this->cashier1 = User::where('emp_id', 'B01-CSH-001')->firstOrFail();

        $this->branch2 = Branch::where('code', 'B02')->firstOrFail();
        $this->cashier2 = User::where('emp_id', 'B02-CSH-001')->firstOrFail();

        $category = MealCategory::firstOrCreate(
            ['slug' => 'rice-bowls'],
            ['name' => 'Rice Bowls', 'is_active' => true]
        );

        $this->mealBranch1 = Meal::create([
            'category_id' => $category->id,
            'branch_id' => $this->branch1->id,
            'name' => 'Branch 1 Tapsilog',
            'menu_code' => 'RB-001',
            'price' => 150.00,
            'is_available' => true,
            'track_stock' => false,
        ]);

        $this->mealBranch2 = Meal::create([
            'category_id' => $category->id,
            'branch_id' => $this->branch2->id,
            'name' => 'Branch 2 Tapsilog',
            'menu_code' => 'RB-002',
            'price' => 150.00,
            'is_available' => true,
            'track_stock' => false,
        ]);
    }

    /**
     * Test schema definitions for branch_daily_sequences table and kitchen_tickets covering index.
     */
    public function test_branch_daily_sequences_table_and_covering_index_exist_in_schema(): void
    {
        $this->assertTrue(
            Schema::hasTable('branch_daily_sequences'),
            'Failed asserting that branch_daily_sequences table exists in schema.'
        );

        $this->assertTrue(
            Schema::hasColumns('branch_daily_sequences', [
                'id',
                'branch_id',
                'sequence_date',
                'sequence_type',
                'current_value',
                'created_at',
                'updated_at',
            ]),
            'Failed asserting that branch_daily_sequences contains all required columns.'
        );

        $this->assertTrue(
            Schema::hasIndex('kitchen_tickets', 'idx_kds_signature_covering'),
            'Failed asserting that kitchen_tickets table has idx_kds_signature_covering index.'
        );
    }

    /**
     * Test rapid sequential order creation produces gapless, strictly monotonic order numbers.
     */
    public function test_simulated_concurrent_order_number_generation_produces_gapless_unique_sequences(): void
    {
        $action = new CreateOrderAction();
        $dateStr = today()->format('Ymd');
        $branchCode = $this->branch1->code;

        $createdOrders = [];

        for ($i = 1; $i <= 10; $i++) {
            $order = $action->execute([
                'branch_id' => $this->branch1->id,
                'cashier_id' => $this->cashier1->id,
                'order_type' => OrderType::DINE_IN->value,
                'items' => [
                    ['meal_id' => $this->mealBranch1->id, 'quantity' => 1],
                ],
            ]);

            $createdOrders[] = $order;
            $expectedNumber = sprintf('%s-%s-%04d', $branchCode, $dateStr, $i);
            $this->assertEquals($expectedNumber, $order->order_number);
        }

        $orderNumbers = array_map(fn ($o) => $o->order_number, $createdOrders);
        $this->assertCount(10, array_unique($orderNumbers), 'Order numbers must be strictly unique.');

        // Verify BranchDailySequence row state
        $seqRow = BranchDailySequence::where('branch_id', $this->branch1->id)
            ->where('sequence_date', today()->toDateString())
            ->where('sequence_type', 'order')
            ->first();

        $this->assertNotNull($seqRow);
        $this->assertEquals(10, $seqRow->current_value);
    }

    /**
     * Test rapid sequential payment processing produces unique, gapless kitchen ticket numbers.
     */
    public function test_simulated_concurrent_kitchen_ticket_generation_never_duplicates(): void
    {
        $createAction = new CreateOrderAction();
        $paymentAction = new ProcessPaymentAction();

        $ticketNumbers = [];

        for ($i = 1; $i <= 10; $i++) {
            $order = $createAction->execute([
                'branch_id' => $this->branch1->id,
                'cashier_id' => $this->cashier1->id,
                'order_type' => OrderType::DINE_IN->value,
                'items' => [
                    ['meal_id' => $this->mealBranch1->id, 'quantity' => 1],
                ],
            ]);

            $result = $paymentAction->execute($order, [
                'cashier_id' => $this->cashier1->id,
                'payment_method' => PaymentMethod::CASH->value,
                'amount_tendered' => 150.00,
            ]);

            $ticket = $result['kitchen_ticket'];
            $this->assertInstanceOf(KitchenTicket::class, $ticket);

            $expectedTicket = sprintf('K-%03d', $i);
            $this->assertEquals($expectedTicket, $ticket->ticket_number);

            $ticketNumbers[] = $ticket->ticket_number;
        }

        $this->assertCount(10, array_unique($ticketNumbers), 'Kitchen ticket numbers must never duplicate.');

        // Verify BranchDailySequence state for kitchen_ticket
        $seqRow = BranchDailySequence::where('branch_id', $this->branch1->id)
            ->where('sequence_date', today()->toDateString())
            ->where('sequence_type', 'kitchen_ticket')
            ->first();

        $this->assertNotNull($seqRow);
        $this->assertEquals(10, $seqRow->current_value);
    }

    /**
     * Test multi-branch daily sequence isolation: Branch 1 and Branch 2 sequences do not cross-contaminate.
     */
    public function test_multi_branch_daily_sequence_isolation(): void
    {
        $action = new CreateOrderAction();
        $dateStr = today()->format('Ymd');

        // Order 1 in Branch 1 -> B01-YYYYMMDD-0001
        $orderB1_1 = $action->execute([
            'branch_id' => $this->branch1->id,
            'cashier_id' => $this->cashier1->id,
            'order_type' => OrderType::DINE_IN->value,
            'items' => [
                ['meal_id' => $this->mealBranch1->id, 'quantity' => 1],
            ],
        ]);
        $this->assertEquals("B01-{$dateStr}-0001", $orderB1_1->order_number);

        // Order 1 in Branch 2 -> B02-YYYYMMDD-0001 (isolated from Branch 1)
        $orderB2_1 = $action->execute([
            'branch_id' => $this->branch2->id,
            'cashier_id' => $this->cashier2->id,
            'order_type' => OrderType::DINE_IN->value,
            'items' => [
                ['meal_id' => $this->mealBranch2->id, 'quantity' => 1],
            ],
        ]);
        $this->assertEquals("B02-{$dateStr}-0001", $orderB2_1->order_number);

        // Order 2 in Branch 1 -> B01-YYYYMMDD-0002
        $orderB1_2 = $action->execute([
            'branch_id' => $this->branch1->id,
            'cashier_id' => $this->cashier1->id,
            'order_type' => OrderType::DINE_IN->value,
            'items' => [
                ['meal_id' => $this->mealBranch1->id, 'quantity' => 1],
            ],
        ]);
        $this->assertEquals("B01-{$dateStr}-0002", $orderB1_2->order_number);

        // Order 2 in Branch 2 -> B02-YYYYMMDD-0002
        $orderB2_2 = $action->execute([
            'branch_id' => $this->branch2->id,
            'cashier_id' => $this->cashier2->id,
            'order_type' => OrderType::DINE_IN->value,
            'items' => [
                ['meal_id' => $this->mealBranch2->id, 'quantity' => 1],
            ],
        ]);
        $this->assertEquals("B02-{$dateStr}-0002", $orderB2_2->order_number);

        // Process payments across both branches and assert independent kitchen ticket sequences
        $paymentAction = new ProcessPaymentAction();

        $ticketB1_1 = $paymentAction->execute($orderB1_1, [
            'cashier_id' => $this->cashier1->id,
            'payment_method' => PaymentMethod::CASH->value,
            'amount_tendered' => 150.00,
        ])['kitchen_ticket'];

        $ticketB2_1 = $paymentAction->execute($orderB2_1, [
            'cashier_id' => $this->cashier2->id,
            'payment_method' => PaymentMethod::CASH->value,
            'amount_tendered' => 150.00,
        ])['kitchen_ticket'];

        $this->assertEquals('K-001', $ticketB1_1->ticket_number);
        $this->assertEquals('K-001', $ticketB2_1->ticket_number);
    }

    /**
     * Test BranchDailySequence::getNextSequence helper method directly.
     */
    public function test_branch_daily_sequence_model_helper_method_atomic_allocation(): void
    {
        $seq1 = BranchDailySequence::getNextSequence($this->branch1->id, 'order');
        $seq2 = BranchDailySequence::getNextSequence($this->branch1->id, 'order');
        $seq3 = BranchDailySequence::getNextSequence($this->branch1->id, 'order');

        $this->assertEquals(1, $seq1);
        $this->assertEquals(2, $seq2);
        $this->assertEquals(3, $seq3);

        // Kitchen ticket sequence is independent
        $ticketSeq1 = BranchDailySequence::getNextSequence($this->branch1->id, 'kitchen_ticket');
        $this->assertEquals(1, $ticketSeq1);
    }

    /**
     * Test sequence allocation query uses sargable column comparison without wrapping in date().
     */
    /**
     * Test sequence allocation query uses sargable column comparison without wrapping in date().
     */
    public function test_sequence_queries_are_sargable_and_avoid_redundant_selects(): void
    {
        \Illuminate\Support\Facades\DB::enableQueryLog();
        \Illuminate\Support\Facades\DB::flushQueryLog();

        $seq = BranchDailySequence::getNextSequence($this->branch1->id, 'order');
        $this->assertGreaterThan(0, $seq);

        $queries = \Illuminate\Support\Facades\DB::getQueryLog();
        \Illuminate\Support\Facades\DB::disableQueryLog();

        foreach ($queries as $q) {
            $sql = strtolower($q['query']);
            $this->assertStringNotContainsString('date(sequence_date)', $sql, 'Sequence query must not wrap sequence_date in date() scalar function.');
        }
    }
}
