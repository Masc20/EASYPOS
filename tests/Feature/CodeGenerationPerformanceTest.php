<?php

namespace Tests\Feature;

use App\Domains\Branches\Models\Branch;
use App\Domains\Identity\Models\User;
use App\Domains\Kitchen\Models\KitchenTicket;
use App\Domains\Menu\Models\Meal;
use App\Domains\Menu\Models\MealCategory;
use App\Domains\PointOfSale\Actions\CreateOrderAction;
use App\Domains\PointOfSale\Actions\ProcessPaymentAction;
use App\Domains\PointOfSale\Models\Order;
use App\Domains\PointOfSale\Models\Payment;
use App\Support\Enums\OrderStatus;
use App\Support\Enums\OrderType;
use App\Support\Enums\PaymentMethod;
use App\Support\Enums\PaymentStatus;
use App\Support\Enums\StaffRole;
use App\Support\Services\CodeGeneratorService;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CodeGenerationPerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branch;
    protected User $cashier;
    protected MealCategory $burgerCategory;
    protected MealCategory $drinksCategory;
    protected Meal $burgerMeal;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->branch = Branch::where('code', 'B01')->firstOrFail();
        $this->cashier = User::where('emp_id', 'B01-CSH-001')->firstOrFail();

        $this->burgerCategory = MealCategory::firstOrCreate(['slug' => 'burgers'], [
            'name' => 'Burgers',
            'is_active' => true,
        ]);

        $this->drinksCategory = MealCategory::firstOrCreate(['slug' => 'drinks'], [
            'name' => 'Drinks',
            'is_active' => true,
        ]);

        $this->burgerMeal = Meal::create([
            'category_id' => $this->burgerCategory->id,
            'branch_id' => $this->branch->id,
            'name' => 'Double Bacon Cheese',
            'menu_code' => 'B-001',
            'price' => 220.00,
            'track_stock' => true,
            'stock_quantity' => 50,
            'is_available' => true,
        ]);
    }

    public function test_code_generator_generates_correct_employee_id_sequences_via_direct_sql(): void
    {
        // 1. Initial next cashier ID for B01 (B01-CSH-001 is seeded)
        $nextCashierId = CodeGeneratorService::generateEmpId($this->branch->id, StaffRole::CASHIER->value);
        $this->assertEquals('B01-CSH-002', $nextCashierId);

        // Create 2nd cashier
        User::create([
            'name' => 'Cashier Two',
            'emp_id' => $nextCashierId,
            'branch_id' => $this->branch->id,
            'pin_code' => Hash::make('2222'),
            'is_active' => true,
        ]);

        // Next cashier ID should be 003
        $this->assertEquals('B01-CSH-003', CodeGeneratorService::generateEmpId($this->branch->id, StaffRole::CASHIER->value));

        // 2. Branch 3 (fresh branch without existing staff) should start at 001
        $branch3 = Branch::create(['name' => 'Davao Branch', 'code' => 'B03', 'is_active' => true]);
        $this->assertEquals('B03-CSH-001', CodeGeneratorService::generateEmpId($branch3->id, StaffRole::CASHIER->value));
        $this->assertEquals('B03-COK-001', CodeGeneratorService::generateEmpId($branch3->id, StaffRole::COOK->value));

        // 3. HQ owner / admin ID should use HQ prefix
        $this->assertEquals('HQ-ADM-001', CodeGeneratorService::generateEmpId(null, StaffRole::OWNER->value));

        // 4. Test higher sequence numbers (e.g. 099 -> 100)
        User::create([
            'name' => 'Cashier Ninety Nine',
            'emp_id' => 'B03-CSH-099',
            'branch_id' => $branch3->id,
            'pin_code' => Hash::make('9999'),
            'is_active' => true,
        ]);

        $this->assertEquals('B03-CSH-100', CodeGeneratorService::generateEmpId($branch3->id, StaffRole::CASHIER->value));
    }

    public function test_code_generator_generates_menu_codes_and_respects_soft_deleted_meals(): void
    {
        // Category Burgers has 1 meal with B-001
        $nextBurgerCode = CodeGeneratorService::generateMenuCode($this->burgerCategory->id);
        $this->assertEquals('B-002', $nextBurgerCode);

        // Create B-002
        $secondMeal = Meal::create([
            'category_id' => $this->burgerCategory->id,
            'name' => 'Chicken Burger',
            'menu_code' => $nextBurgerCode,
            'price' => 190.00,
            'is_available' => true,
        ]);

        $this->assertEquals('B-003', CodeGeneratorService::generateMenuCode($this->burgerCategory->id));

        // Soft delete B-002
        $secondMeal->delete();
        $this->assertTrue($secondMeal->trashed());

        // Even though B-002 is soft-deleted, generateMenuCode must query withTrashed() and still return B-003
        $this->assertEquals('B-003', CodeGeneratorService::generateMenuCode($this->burgerCategory->id));

        // Drinks category starts at D-001
        $this->assertEquals('D-001', CodeGeneratorService::generateMenuCode($this->drinksCategory->id));
    }

    public function test_code_generator_generates_branch_code_candidates_and_sequential_fallbacks(): void
    {
        // 1. Two-word name produces 2+2 acronym
        $code1 = CodeGeneratorService::generateBranchCode('Ortigas Pasig');
        $this->assertEquals('ORPA', $code1);

        // 2. Single word name produces 4-letter code
        $code2 = CodeGeneratorService::generateBranchCode('Alabang');
        $this->assertEquals('ALAB', $code2);

        // 3. Collision avoidance: create branch with ORPA
        Branch::create(['name' => 'Ortigas Pasig', 'code' => 'ORPA', 'is_active' => true]);

        // Second attempt with same name cannot use ORPA, falls back to sequential B{NN}
        $code3 = CodeGeneratorService::generateBranchCode('Ortigas Pasig');
        $this->assertNotEquals('ORPA', $code3);
        $this->assertMatchesRegularExpression('/^B\d{2}$/', $code3);

        // 4. Blank or null branch name generates sequential B{NN}
        $codeNull = CodeGeneratorService::generateBranchCode(null);
        $this->assertMatchesRegularExpression('/^B\d{2}$/', $codeNull);
    }

    public function test_half_open_timestamp_query_matches_exact_date_boundaries(): void
    {
        $branch = $this->branch;
        $now = Carbon::parse('2026-11-10 14:00:00');
        Carbon::setTestNow($now);

        $startOfDay = today()->startOfDay();     // 2026-11-10 00:00:00
        $endOfDay = today()->addDay()->startOfDay(); // 2026-11-11 00:00:00

        // 1. Order created yesterday at 23:59:59 (outside range)
        Order::create([
            'order_number' => 'B01-YESTERDAY-0001',
            'branch_id' => $branch->id,
            'cashier_id' => $this->cashier->id,
            'order_type' => OrderType::DINE_IN->value,
            'subtotal' => 100.00,
            'total_amount' => 100.00,
            'created_at' => $startOfDay->copy()->subSecond(),
            'updated_at' => $startOfDay->copy()->subSecond(),
        ]);

        // 2. Order created exactly at startOfDay (00:00:00) (inside range)
        Order::create([
            'order_number' => 'B01-TODAY-0001',
            'branch_id' => $branch->id,
            'cashier_id' => $this->cashier->id,
            'order_type' => OrderType::DINE_IN->value,
            'subtotal' => 100.00,
            'total_amount' => 100.00,
            'created_at' => $startOfDay,
            'updated_at' => $startOfDay,
        ]);

        // 3. Order created today at 12:00:00 (inside range)
        Order::create([
            'order_number' => 'B01-TODAY-0002',
            'branch_id' => $branch->id,
            'cashier_id' => $this->cashier->id,
            'order_type' => OrderType::DINE_IN->value,
            'subtotal' => 100.00,
            'total_amount' => 100.00,
            'created_at' => $startOfDay->copy()->addHours(12),
            'updated_at' => $startOfDay->copy()->addHours(12),
        ]);

        // 4. Order created today at 23:59:59 (inside range)
        Order::create([
            'order_number' => 'B01-TODAY-0003',
            'branch_id' => $branch->id,
            'cashier_id' => $this->cashier->id,
            'order_type' => OrderType::DINE_IN->value,
            'subtotal' => 100.00,
            'total_amount' => 100.00,
            'created_at' => $endOfDay->copy()->subSecond(),
            'updated_at' => $endOfDay->copy()->subSecond(),
        ]);

        // 5. Order created tomorrow at 00:00:00 (outside range)
        Order::create([
            'order_number' => 'B01-TOMORROW-0001',
            'branch_id' => $branch->id,
            'cashier_id' => $this->cashier->id,
            'order_type' => OrderType::DINE_IN->value,
            'subtotal' => 100.00,
            'total_amount' => 100.00,
            'created_at' => $endOfDay,
            'updated_at' => $endOfDay,
        ]);

        $count = Order::where('branch_id', $branch->id)
            ->where('created_at', '>=', $startOfDay)
            ->where('created_at', '<', $endOfDay)
            ->count();

        // Exactly 3 orders fall within today's half-open range
        $this->assertEquals(3, $count);

        Carbon::setTestNow(); // reset
    }

    public function test_create_order_action_retries_and_resolves_order_number_collision(): void
    {
        $action = new CreateOrderAction();
        $dateStr = now()->format('Ymd');
        $branchCode = $this->branch->code;

        // Pre-create an order with sequence 0001 manually to cause a conflict
        $conflictingOrderNumber = "{$branchCode}-{$dateStr}-0001";
        Order::create([
            'order_number' => $conflictingOrderNumber,
            'branch_id' => $this->branch->id,
            'cashier_id' => $this->cashier->id,
            'order_type' => OrderType::DINE_IN->value,
            'subtotal' => 220.00,
            'total_amount' => 220.00,
            'status' => OrderStatus::PENDING->value,
            'payment_status' => PaymentStatus::UNPAID->value,
        ]);

        $initialStock = $this->burgerMeal->fresh()->stock_quantity;

        // When creating a new order, it should avoid 0001 and allocate 0002 cleanly
        $newOrder = $action->execute([
            'branch_id' => $this->branch->id,
            'cashier_id' => $this->cashier->id,
            'order_type' => OrderType::DINE_IN->value,
            'items' => [
                ['meal_id' => $this->burgerMeal->id, 'quantity' => 2],
            ],
        ]);

        $this->assertNotEquals($conflictingOrderNumber, $newOrder->order_number);
        $this->assertEquals("{$branchCode}-{$dateStr}-0002", $newOrder->order_number);

        // Stock was properly decremented once from 50 to 48
        $this->assertEquals($initialStock - 2, $this->burgerMeal->fresh()->stock_quantity);
    }

    public function test_reporting_and_reconciliation_composite_indexes_exist_in_schema(): void
    {
        // 1. Orders table composite indexes
        $this->assertTrue(
            Schema::hasIndex('orders', 'idx_orders_global_reporting'),
            'Failed asserting that orders table has idx_orders_global_reporting index.'
        );

        $this->assertTrue(
            Schema::hasIndex('orders', 'idx_orders_branch_reporting'),
            'Failed asserting that orders table has idx_orders_branch_reporting index.'
        );

        $this->assertTrue(
            Schema::hasIndex('orders', 'idx_orders_cashier_reporting'),
            'Failed asserting that orders table has idx_orders_cashier_reporting index.'
        );

        // 2. Payments table composite indexes
        $this->assertTrue(
            Schema::hasIndex('payments', 'idx_payments_branch_reconciliation'),
            'Failed asserting that payments table has idx_payments_branch_reconciliation index.'
        );

        $this->assertTrue(
            Schema::hasIndex('payments', 'idx_payments_global_reconciliation'),
            'Failed asserting that payments table has idx_payments_global_reconciliation index.'
        );
    }

    public function test_kitchen_ticket_sequence_uses_half_open_range_and_increments_properly(): void
    {
        $order = (new CreateOrderAction())->execute([
            'branch_id' => $this->branch->id,
            'cashier_id' => $this->cashier->id,
            'order_type' => OrderType::DINE_IN->value,
            'items' => [
                ['meal_id' => $this->burgerMeal->id, 'quantity' => 1],
            ],
        ]);

        $paymentAction = new ProcessPaymentAction();
        $result = $paymentAction->execute($order, [
            'cashier_id' => $this->cashier->id,
            'payment_method' => PaymentMethod::CASH->value,
            'amount_tendered' => 220.00,
        ]);

        $this->assertInstanceOf(KitchenTicket::class, $result['kitchen_ticket']);
        $this->assertEquals('K-001', $result['kitchen_ticket']->ticket_number);

        // Create second order and process payment
        $order2 = (new CreateOrderAction())->execute([
            'branch_id' => $this->branch->id,
            'cashier_id' => $this->cashier->id,
            'order_type' => OrderType::DINE_IN->value,
            'items' => [
                ['meal_id' => $this->burgerMeal->id, 'quantity' => 1],
            ],
        ]);

        $result2 = $paymentAction->execute($order2, [
            'cashier_id' => $this->cashier->id,
            'payment_method' => PaymentMethod::CASH->value,
            'amount_tendered' => 220.00,
        ]);

        $this->assertEquals('K-002', $result2['kitchen_ticket']->ticket_number);
    }
}
