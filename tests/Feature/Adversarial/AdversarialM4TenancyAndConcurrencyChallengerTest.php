<?php

namespace Tests\Feature\Adversarial;

use App\Domains\Branches\Models\Branch;
use App\Domains\Identity\Models\User;
use App\Domains\Kitchen\Livewire\KitchenDisplay;
use App\Domains\Kitchen\Models\KitchenTicket;
use App\Domains\Kitchen\Models\KitchenTicketItem;
use App\Domains\Menu\Models\Meal;
use App\Domains\Menu\Models\MealCategory;
use App\Domains\PointOfSale\Actions\CreateOrderAction;
use App\Domains\PointOfSale\Livewire\Checkout\CheckoutModal;
use App\Domains\PointOfSale\Models\BranchDailySequence;
use App\Domains\PointOfSale\Models\Order;
use App\Domains\PointOfSale\Models\OrderItem;
use App\Domains\PointOfSale\Models\Payment;
use App\Domains\PointOfSale\Services\SalesAggregationService;
use App\Filament\Pages\SalesRecords;
use App\Support\Enums\OrderStatus;
use App\Support\Enums\OrderType;
use App\Support\Enums\PaymentMethod;
use App\Support\Enums\PaymentStatus;
use App\Support\Enums\StaffRole;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

class AdversarialM4TenancyAndConcurrencyChallengerTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branchA;
    protected Branch $branchB;
    protected Branch $branchC;
    protected User $managerA;
    protected User $cashierA;
    protected User $cashierB;
    protected User $cookA;
    protected MealCategory $category;
    protected Meal $mealA;
    protected Meal $mealB;
    protected Order $orderA;
    protected Order $orderB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->branchA = Branch::firstOrCreate(['code' => 'B01'], ['name' => 'Branch A', 'is_active' => true]);
        $this->branchB = Branch::firstOrCreate(['code' => 'B02'], ['name' => 'Branch B', 'is_active' => true]);
        $this->branchC = Branch::firstOrCreate(['code' => 'B03'], ['name' => 'Branch C', 'is_active' => true]);

        $this->category = MealCategory::firstOrCreate(['slug' => 'rice-meals'], ['name' => 'Rice Meals', 'is_active' => true]);

        $this->managerA = User::create([
            'name' => 'Manager Alpha',
            'email' => 'mgr_a_challenger@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'B01-MGR-CH1',
            'pin_code' => Hash::make('1111'),
            'branch_id' => $this->branchA->id,
            'is_active' => true,
        ]);
        $this->managerA->assignRole(StaffRole::BRANCH_MANAGER->value);
        $this->managerA->givePermissionTo(['reports.view', 'pos.access', 'menu.manage', 'users.manage']);

        $this->cashierA = User::create([
            'name' => 'Cashier Alpha',
            'email' => 'csh_a_challenger@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'B01-CSH-CH1',
            'pin_code' => Hash::make('1234'),
            'branch_id' => $this->branchA->id,
            'is_active' => true,
        ]);
        $this->cashierA->assignRole(StaffRole::CASHIER->value);

        $this->cashierB = User::create([
            'name' => 'Cashier Beta',
            'email' => 'csh_b_challenger@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'B02-CSH-CH1',
            'pin_code' => Hash::make('5678'),
            'branch_id' => $this->branchB->id,
            'is_active' => true,
        ]);
        $this->cashierB->assignRole(StaffRole::CASHIER->value);

        $this->cookA = User::create([
            'name' => 'Cook Alpha',
            'email' => 'cok_a_challenger@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'B01-COK-CH1',
            'pin_code' => Hash::make('9999'),
            'branch_id' => $this->branchA->id,
            'is_active' => true,
        ]);
        $this->cookA->assignRole(StaffRole::COOK->value);

        $this->mealA = Meal::create([
            'category_id' => $this->category->id,
            'branch_id' => $this->branchA->id,
            'name' => 'Meal Alpha',
            'menu_code' => 'MLA-001',
            'price' => 100.00,
            'is_available' => true,
            'track_stock' => true,
            'stock_quantity' => 10,
        ]);

        $this->mealB = Meal::create([
            'category_id' => $this->category->id,
            'branch_id' => $this->branchB->id,
            'name' => 'Meal Beta',
            'menu_code' => 'MLB-001',
            'price' => 250.00,
            'is_available' => true,
            'track_stock' => true,
            'stock_quantity' => 10,
        ]);

        $createOrder = new CreateOrderAction();
        $this->orderA = $createOrder->execute([
            'branch_id' => $this->branchA->id,
            'cashier_id' => $this->cashierA->id,
            'order_type' => OrderType::DINE_IN->value,
            'items' => [['meal_id' => $this->mealA->id, 'quantity' => 2]],
        ]);
        $this->orderA->update(['payment_status' => PaymentStatus::PAID->value, 'status' => OrderStatus::COMPLETED->value]);
        Payment::create([
            'order_id' => $this->orderA->id,
            'branch_id' => $this->branchA->id,
            'cashier_id' => $this->cashierA->id,
            'payment_method' => PaymentMethod::CASH->value,
            'amount' => 200.00,
            'amount_tendered' => 200.00,
            'change_given' => 0.00,
            'status' => 'successful',
        ]);

        $this->orderB = $createOrder->execute([
            'branch_id' => $this->branchB->id,
            'cashier_id' => $this->cashierB->id,
            'order_type' => OrderType::DINE_IN->value,
            'items' => [['meal_id' => $this->mealB->id, 'quantity' => 1]],
        ]);
        $this->orderB->update(['payment_status' => PaymentStatus::PAID->value, 'status' => OrderStatus::COMPLETED->value]);
        Payment::create([
            'order_id' => $this->orderB->id,
            'branch_id' => $this->branchB->id,
            'cashier_id' => $this->cashierB->id,
            'payment_method' => PaymentMethod::QR_TRANSFER->value,
            'amount' => 250.00,
            'amount_tendered' => 250.00,
            'change_given' => 0.00,
            'status' => 'successful',
        ]);
    }

    /**
     * 1. Adversarial Test: Branch Manager attempts cross-branch CSV export tampering.
     */
    public function test_branch_manager_cross_branch_csv_export_tampering_is_strictly_contained(): void
    {
        $this->actingAs($this->managerA);

        $component = Livewire::test(SalesRecords::class);
        $component->set('branchId', $this->branchB->id);
        $component->set('cashierId', $this->cashierB->id);

        $response = $component->instance()->exportCsv(app(SalesAggregationService::class));
        ob_start();
        $response->sendContent();
        $csvContent = ob_get_clean();

        $this->assertStringContainsString($this->orderA->order_number, $csvContent);
        $this->assertStringNotContainsString($this->orderB->order_number, $csvContent);
        $this->assertStringNotContainsString('Branch Beta', $csvContent);

        // Also test periodic CSV export with spoofed branch
        $periodicResponse = $component->instance()->exportPeriodicCsv(app(SalesAggregationService::class));
        ob_start();
        $periodicResponse->sendContent();
        $periodicCsv = ob_get_clean();

        $this->assertStringContainsString('200.00', $periodicCsv, 'Must reflect Branch A 200.00 sales');
        $this->assertStringNotContainsString('250.00', $periodicCsv, 'Must NOT reflect Branch B 250.00 sales');
    }

    /**
     * 2. Adversarial Test: Direct SalesAggregationService calls with spoofed branch parameters by scoped manager.
     */
    public function test_sales_aggregation_service_tampering_by_scoped_manager_forces_own_branch(): void
    {
        $this->actingAs($this->managerA);
        $service = app(SalesAggregationService::class);

        // Adversarial inputs: foreign branch, -1, 999999, null
        $spoofedInputs = [$this->branchB->id, -1, 999999, null];

        foreach ($spoofedInputs as $spoofedBranchId) {
            $summary = $service->getSummary(null, null, $spoofedBranchId);
            $this->assertEquals(200.00, $summary['net_sales'], "Spoofed branch [{$spoofedBranchId}] must not leak foreign data!");
            $this->assertEquals(1, $summary['orders_count']);

            $periodic = $service->getPeriodicReport('day', null, null, $spoofedBranchId);
            $this->assertEquals(200.00, $periodic['totals']['net_sales']);

            $rankings = $service->getBestSellingRankings(null, null, $spoofedBranchId);
            $mealNames = array_column($rankings, 'name');
            $this->assertContains('Meal Alpha', $mealNames);
            $this->assertNotContains('Meal Beta', $mealNames);
        }
    }

    /**
     * 3. Adversarial Test: Cashier cross-branch checkout tampering is rejected at write boundary.
     */
    public function test_cashier_cross_branch_checkout_tampering_rejected_at_write_boundary(): void
    {
        $this->actingAs($this->cashierA);
        $action = new CreateOrderAction();

        // Scenario 1: Cashier A passes foreign branch ID with foreign meal
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Meal 'Meal Beta' is not available for this branch.");
        $action->execute([
            'branch_id' => $this->branchA->id,
            'cashier_id' => $this->cashierA->id,
            'order_type' => OrderType::DINE_IN->value,
            'items' => [['meal_id' => $this->mealB->id, 'quantity' => 1]],
        ]);
    }

    /**
     * 4. Adversarial Test: Checkout with non-existent or negative branch IDs fails fast with ModelNotFoundException.
     */
    public function test_checkout_with_invalid_branch_ids_fails_safely(): void
    {
        $this->actingAs($this->cashierA);
        $action = new CreateOrderAction();

        foreach ([-1, 999999] as $invalidBranchId) {
            try {
                $action->execute([
                    'branch_id' => $invalidBranchId,
                    'cashier_id' => $this->cashierA->id,
                    'order_type' => OrderType::DINE_IN->value,
                    'items' => [['meal_id' => $this->mealA->id, 'quantity' => 1]],
                ]);
                $this->fail("Expected ModelNotFoundException for invalid branch_id [{$invalidBranchId}]");
            } catch (ModelNotFoundException $e) {
                $this->assertTrue(true);
            }
        }
    }

    /**
     * 5. Adversarial Test: Kitchen Display cross-branch mutations throw ModelNotFoundException.
     */
    public function test_kitchen_display_cross_branch_mutation_attacks_rejected(): void
    {
        $this->actingAs($this->cookA);

        // Create kitchen ticket belonging to Branch B
        $ticketB = KitchenTicket::create([
            'order_id' => $this->orderB->id,
            'branch_id' => $this->branchB->id,
            'ticket_number' => 'B02-TKT-0001',
            'status' => 'new',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $itemB = KitchenTicketItem::create([
            'ticket_id' => $ticketB->id,
            'order_item_id' => $this->orderB->items()->first()->id,
            'status' => 'pending',
        ]);

        $component = Livewire::test(KitchenDisplay::class);

        // Cook A attempts to claim Branch B ticket
        try {
            $component->call('claimTicket', $ticketB->id);
            $this->fail('Expected ModelNotFoundException when claiming ticket from another branch');
        } catch (ModelNotFoundException $e) {
            $this->assertTrue(true);
        }

        // Cook A attempts to mark ready Branch B ticket
        try {
            $component->call('markReady', $ticketB->id);
            $this->fail('Expected ModelNotFoundException when marking ready ticket from another branch');
        } catch (ModelNotFoundException $e) {
            $this->assertTrue(true);
        }

        // Cook A attempts to toggle item on Branch B ticket
        try {
            $component->call('toggleItem', $itemB->id);
            $this->fail('Expected ModelNotFoundException when toggling item from another branch');
        } catch (ModelNotFoundException $e) {
            $this->assertTrue(true);
        }
    }

    /**
     * 6. Adversarial Test: Unique composite constraint integrity on branch_daily_sequences.
     */
    public function test_branch_daily_sequence_unique_composite_constraint(): void
    {
        $date = '2026-11-20';
        BranchDailySequence::create([
            'branch_id' => $this->branchA->id,
            'sequence_date' => $date,
            'sequence_type' => 'order',
            'current_value' => 5,
        ]);

        // Attempt duplicate insert of exact composite key: [branch_id, sequence_date, sequence_type]
        $this->expectException(QueryException::class);
        BranchDailySequence::create([
            'branch_id' => $this->branchA->id,
            'sequence_date' => $date,
            'sequence_type' => 'order',
            'current_value' => 10,
        ]);
    }

    /**
     * 7. Adversarial Test: High-concurrency interleaved sequence allocation across 3 branches & 2 types.
     */
    public function test_high_volume_interleaved_daily_sequence_isolation(): void
    {
        // Keep this isolated sequence date separate from orders created in setUp() today.
        $date = today()->addYears(1)->toDateString();
        $branches = [$this->branchA->id, $this->branchB->id, $this->branchC->id];
        $types = ['order', 'kitchen_ticket'];

        $allocations = [];
        foreach ($branches as $b) {
            foreach ($types as $t) {
                $allocations["{$b}_{$t}"] = [];
            }
        }

        // Interleave allocations in round-robin fashion
        for ($round = 1; $round <= 25; $round++) {
            foreach ($branches as $b) {
                foreach ($types as $t) {
                    $seq = BranchDailySequence::getNextSequence($b, $t, $date);
                    $allocations["{$b}_{$t}"][] = $seq;
                }
            }
        }

        // Verify each stream is strictly sequential [1..25] with zero collisions and zero gaps
        foreach ($allocations as $key => $seqs) {
            $this->assertCount(25, $seqs);
            $this->assertSame(range(1, 25), $seqs, "Stream [{$key}] must be strictly sequential 1..25 with 0 gaps.");
            $this->assertSame($seqs, array_unique($seqs), "Stream [{$key}] must have 0 duplicate values.");
        }
    }

    /**
     * 8. Adversarial Test: Pessimistic stock locking depletion, exhaustion, and auto-disabling.
     */
    public function test_pessimistic_stock_locking_depletion_and_exhaustion(): void
    {
        $stockMeal = Meal::create([
            'category_id' => $this->category->id,
            'branch_id' => $this->branchA->id,
            'name' => 'Limited Salmon',
            'menu_code' => 'LTD-SALMON',
            'price' => 300.00,
            'is_available' => true,
            'track_stock' => true,
            'stock_quantity' => 3,
        ]);

        $createOrder = new CreateOrderAction();

        // Order 1: Take 2 portions (stock 3 -> 1)
        $order1 = $createOrder->execute([
            'branch_id' => $this->branchA->id,
            'cashier_id' => $this->cashierA->id,
            'order_type' => OrderType::DINE_IN->value,
            'items' => [['meal_id' => $stockMeal->id, 'quantity' => 2]],
        ]);
        $this->assertNotNull($order1);
        $this->assertSame(1, $stockMeal->fresh()->stock_quantity);
        $this->assertTrue($stockMeal->fresh()->is_available);

        // Order 2: Attempt 2 portions when only 1 is left -> MUST fail and rollback
        try {
            $createOrder->execute([
                'branch_id' => $this->branchA->id,
                'cashier_id' => $this->cashierA->id,
                'order_type' => OrderType::DINE_IN->value,
                'items' => [['meal_id' => $stockMeal->id, 'quantity' => 2]],
            ]);
            $this->fail('Expected InvalidArgumentException for stock over-allocation');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Insufficient portions left', $e->getMessage());
        }

        // Stock must still be 1 (zero partial degradation)
        $this->assertSame(1, $stockMeal->fresh()->stock_quantity);

        // Order 3: Take exact remaining portion (stock 1 -> 0, auto-disabled)
        $order3 = $createOrder->execute([
            'branch_id' => $this->branchA->id,
            'cashier_id' => $this->cashierA->id,
            'order_type' => OrderType::DINE_IN->value,
            'items' => [['meal_id' => $stockMeal->id, 'quantity' => 1]],
        ]);
        $this->assertNotNull($order3);
        $this->assertSame(0, $stockMeal->fresh()->stock_quantity);
        $this->assertFalse($stockMeal->fresh()->is_available, 'Depleted tracked meal must be auto-disabled');

        // Order 4: Attempt to order 86d dish -> MUST fail immediately
        try {
            $createOrder->execute([
                'branch_id' => $this->branchA->id,
                'cashier_id' => $this->cashierA->id,
                'order_type' => OrderType::DINE_IN->value,
                'items' => [['meal_id' => $stockMeal->id, 'quantity' => 1]],
            ]);
            $this->fail("Expected InvalidArgumentException for 86'd dish");
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString("marked as 86'd", $e->getMessage());
        }
    }

    /**
     * 9. Adversarial Test: Multi-meal atomic transaction rollback on stock shortfall prevents partial commits.
     */
    public function test_multi_meal_atomic_transaction_rollback_preserves_stock_invariants(): void
    {
        $dish1 = Meal::create([
            'category_id' => $this->category->id,
            'branch_id' => $this->branchA->id,
            'name' => 'Dish One',
            'menu_code' => 'DSH-01',
            'price' => 100.00,
            'is_available' => true,
            'track_stock' => true,
            'stock_quantity' => 10,
        ]);

        $dish2 = Meal::create([
            'category_id' => $this->category->id,
            'branch_id' => $this->branchA->id,
            'name' => 'Dish Two',
            'menu_code' => 'DSH-02',
            'price' => 150.00,
            'is_available' => true,
            'track_stock' => true,
            'stock_quantity' => 1, // Only 1 left
        ]);

        $initialOrderCount = Order::count();
        $createOrder = new CreateOrderAction();

        // Attempt order with 5 of Dish One and 2 of Dish Two (which fails)
        try {
            $createOrder->execute([
                'branch_id' => $this->branchA->id,
                'cashier_id' => $this->cashierA->id,
                'order_type' => OrderType::DINE_IN->value,
                'items' => [
                    ['meal_id' => $dish1->id, 'quantity' => 5],
                    ['meal_id' => $dish2->id, 'quantity' => 2],
                ],
            ]);
            $this->fail('Expected InvalidArgumentException due to Dish Two stock shortfall');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Insufficient portions left for \'Dish Two\'', $e->getMessage());
        }

        // Verify complete atomic rollback: Dish One stock MUST NOT be decremented!
        $this->assertSame(10, $dish1->fresh()->stock_quantity, 'Dish One stock must remain untouched after rollback');
        $this->assertSame(1, $dish2->fresh()->stock_quantity, 'Dish Two stock must remain untouched after rollback');
        $this->assertSame($initialOrderCount, Order::count(), 'Zero phantom orders should be created');
    }
}
