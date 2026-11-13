<?php

namespace Tests\Feature\Adversarial;

use App\Domains\Branches\Models\Branch;
use App\Domains\Identity\Models\User;
use App\Domains\Menu\Models\Meal;
use App\Domains\Menu\Models\MealCategory;
use App\Domains\PointOfSale\Actions\CreateOrderAction;
use App\Domains\PointOfSale\Actions\ProcessPaymentAction;
use App\Domains\PointOfSale\Actions\VoidOrderAction;
use App\Domains\PointOfSale\Models\Order;
use App\Domains\PointOfSale\Services\SalesAggregationService;
use App\Support\Enums\OrderStatus;
use App\Support\Enums\PaymentMethod;
use App\Support\Enums\PaymentStatus;
use App\Support\Enums\StaffRole;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;
use Tests\TestCase;

class AdversarialCheckoutAndAggregationParityTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branch;
    protected User $manager;
    protected User $cashier;
    protected MealCategory $category;
    protected Meal $mealA;
    protected Meal $mealB;
    protected CreateOrderAction $createOrderAction;
    protected ProcessPaymentAction $processPaymentAction;
    protected VoidOrderAction $voidOrderAction;
    protected SalesAggregationService $aggregationService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Cache::flush();

        $this->branch = Branch::firstOrCreate(['code' => 'B01'], [
            'name' => 'Flagship Manila',
            'is_active' => true,
        ]);

        $this->manager = User::create([
            'name' => 'Branch Manager B01',
            'email' => 'mgr_chk_b01@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'B01-MGR-CHK01',
            'pin_code' => Hash::make('1234'),
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);
        $this->manager->assignRole(StaffRole::BRANCH_MANAGER->value);

        $this->cashier = User::create([
            'name' => 'Cashier B01',
            'email' => 'csh_chk_b01@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'B01-CSH-CHK01',
            'pin_code' => Hash::make('1234'),
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);
        $this->cashier->assignRole(StaffRole::CASHIER->value);

        $this->category = MealCategory::firstOrCreate(['slug' => 'mains'], [
            'name' => 'Main Dishes',
            'is_active' => true,
        ]);

        $this->mealA = Meal::create([
            'name' => 'Classic Chicken Adobo',
            'menu_code' => 'B01-ADOB-CHK',
            'category_id' => $this->category->id,
            'branch_id' => $this->branch->id,
            'price' => 280.00,
            'is_available' => true,
            'stock_quantity' => 100,
            'track_stock' => true,
        ]);

        $this->mealB = Meal::create([
            'name' => 'Sinigang na Baboy',
            'menu_code' => 'B01-SINI-CHK',
            'category_id' => $this->category->id,
            'branch_id' => $this->branch->id,
            'price' => 350.00,
            'is_available' => true,
            'stock_quantity' => 100,
            'track_stock' => true,
        ]);

        $this->createOrderAction = app(CreateOrderAction::class);
        $this->processPaymentAction = app(ProcessPaymentAction::class);
        $this->voidOrderAction = app(VoidOrderAction::class);
        $this->aggregationService = app(SalesAggregationService::class);
    }

    /**
     * Test 1: Under-tender rejection in cash payments.
     */
    public function test_checkout_payment_tenders_and_cash_drawer_reconciliation(): void
    {
        $this->actingAs($this->cashier);

        $order = $this->createOrderAction->execute([
            'branch_id' => $this->branch->id,
            'cashier_id' => $this->cashier->id,
            'order_type' => 'dine_in',
            'items' => [['meal_id' => $this->mealA->id, 'quantity' => 1]],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('less than the total due');

        $this->processPaymentAction->execute($order, [
            'cashier_id' => $this->cashier->id,
            'payment_method' => PaymentMethod::CASH->value,
            'amount_tendered' => 200.00,
        ]);
    }

    /**
     * Test 2: Cash change calculation and over-tender recording.
     */
    public function test_cash_change_calculation_and_overtender(): void
    {
        $this->actingAs($this->cashier);

        $order = $this->createOrderAction->execute([
            'branch_id' => $this->branch->id,
            'cashier_id' => $this->cashier->id,
            'order_type' => 'dine_in',
            'items' => [['meal_id' => $this->mealA->id, 'quantity' => 1]],
        ]);

        $result = $this->processPaymentAction->execute($order, [
            'cashier_id' => $this->cashier->id,
            'payment_method' => PaymentMethod::CASH->value,
            'amount_tendered' => 500.00,
        ]);

        $payment = $result['payment'];
        $this->assertEquals(280.00, (float) $payment->amount);
        $this->assertEquals(500.00, (float) $payment->amount_tendered);
        $this->assertEquals(220.00, (float) $payment->change_given);
        $this->assertEquals(PaymentStatus::PAID->value, $order->fresh()->payment_status);
    }

    /**
     * Test 3: Digital payments (QR / Cards) force change_given to 0.00.
     */
    public function test_digital_payment_tenders_and_drawer_isolation(): void
    {
        $this->actingAs($this->cashier);

        $order = $this->createOrderAction->execute([
            'branch_id' => $this->branch->id,
            'cashier_id' => $this->cashier->id,
            'order_type' => 'dine_in',
            'items' => [['meal_id' => $this->mealB->id, 'quantity' => 1]],
        ]);

        $result = $this->processPaymentAction->execute($order, [
            'cashier_id' => $this->cashier->id,
            'payment_method' => PaymentMethod::QR_TRANSFER->value,
            'amount_tendered' => 1000.00,
            'reference_number' => 'QR-REF-112233',
        ]);

        $payment = $result['payment'];
        $this->assertEquals(350.00, (float) $payment->amount);
        $this->assertEquals(350.00, (float) $payment->amount_tendered);
        $this->assertEquals(0.00, (float) $payment->change_given);
    }

    /**
     * Test 4: Prevent duplicate payment.
     */
    public function test_order_cannot_be_paid_twice(): void
    {
        $this->actingAs($this->cashier);

        $order = $this->createOrderAction->execute([
            'branch_id' => $this->branch->id,
            'cashier_id' => $this->cashier->id,
            'order_type' => 'dine_in',
            'items' => [['meal_id' => $this->mealA->id, 'quantity' => 1]],
        ]);

        $this->processPaymentAction->execute($order, [
            'cashier_id' => $this->cashier->id,
            'payment_method' => PaymentMethod::CASH->value,
            'amount_tendered' => 280.00,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('has already been paid');

        $this->processPaymentAction->execute($order->fresh(), [
            'cashier_id' => $this->cashier->id,
            'payment_method' => PaymentMethod::CASH->value,
            'amount_tendered' => 280.00,
        ]);
    }

    /**
     * Test 5: Strict exclusion of voided / refunded orders from sales aggregates.
     */
    public function test_voided_and_refunded_orders_strict_exclusion_from_sales(): void
    {
        $this->actingAs($this->cashier);

        $order1 = $this->createOrderAction->execute([
            'branch_id' => $this->branch->id,
            'cashier_id' => $this->cashier->id,
            'order_type' => 'dine_in',
            'items' => [['meal_id' => $this->mealA->id, 'quantity' => 1]],
        ]);
        $this->processPaymentAction->execute($order1, [
            'cashier_id' => $this->cashier->id,
            'payment_method' => PaymentMethod::CASH->value,
            'amount_tendered' => 280.00,
        ]);

        $order2 = $this->createOrderAction->execute([
            'branch_id' => $this->branch->id,
            'cashier_id' => $this->cashier->id,
            'order_type' => 'dine_in',
            'items' => [['meal_id' => $this->mealB->id, 'quantity' => 1]],
        ]);
        $this->processPaymentAction->execute($order2, [
            'cashier_id' => $this->cashier->id,
            'payment_method' => PaymentMethod::CASH->value,
            'amount_tendered' => 350.00,
        ]);

        // Void Order 2
        $this->voidOrderAction->execute([
            'order_id' => $order2->id,
            'manager_id' => $this->manager->emp_id,
            'manager_pin' => '1234',
            'reason' => 'Customer cancellation',
            'cashier_id' => $this->cashier->id,
        ]);

        $this->aggregationService->clearRuntimeCache();
        Cache::flush();

        $summary = $this->aggregationService->getSummary(null, null, $this->branch->id, null, false);
        $this->assertEquals(280.00, $summary['gross_sales']);
        $this->assertEquals(280.00, $summary['net_sales']);
        $this->assertEquals(1, $summary['orders_count']);
        $this->assertEquals(350.00, $summary['refunds_summary']['refunded_amount']);
        $this->assertEquals(1, $summary['refunds_summary']['refunded_count']);
    }

    /**
     * Test 6: Aggregation Parity across getSummary, PeriodicReport, and CSV exports.
     */
    public function test_aggregation_parity_across_summary_periodic_reports_and_csv(): void
    {
        $this->actingAs($this->cashier);

        // Mix: 1 Cash order, 1 QR order, 1 Senior citizen order
        $o1 = $this->createOrderAction->execute([
            'branch_id' => $this->branch->id,
            'cashier_id' => $this->cashier->id,
            'order_type' => 'dine_in',
            'items' => [['meal_id' => $this->mealA->id, 'quantity' => 1]],
        ]);
        $this->processPaymentAction->execute($o1, [
            'cashier_id' => $this->cashier->id,
            'payment_method' => PaymentMethod::CASH->value,
            'amount_tendered' => 300.00,
        ]);

        $o2 = $this->createOrderAction->execute([
            'branch_id' => $this->branch->id,
            'cashier_id' => $this->cashier->id,
            'order_type' => 'takeout',
            'items' => [['meal_id' => $this->mealB->id, 'quantity' => 1]],
        ]);
        $this->processPaymentAction->execute($o2, [
            'cashier_id' => $this->cashier->id,
            'payment_method' => PaymentMethod::QR_TRANSFER->value,
            'amount_tendered' => 350.00,
        ]);

        $o3 = $this->createOrderAction->execute([
            'branch_id' => $this->branch->id,
            'cashier_id' => $this->cashier->id,
            'order_type' => 'dine_in',
            'discount_type' => 'senior_pwd',
            'items' => [
                ['meal_id' => $this->mealA->id, 'quantity' => 1],
                ['meal_id' => $this->mealB->id, 'quantity' => 1],
            ],
        ]);
        $this->processPaymentAction->execute($o3, [
            'cashier_id' => $this->cashier->id,
            'payment_method' => PaymentMethod::CASH->value,
            'amount_tendered' => 504.00,
        ]);

        $this->aggregationService->clearRuntimeCache();
        Cache::flush();

        $summary = $this->aggregationService->getSummary(null, null, $this->branch->id, null, false);
        $periodic = $this->aggregationService->getPeriodicReport('day', null, null, $this->branch->id);
        $totals = $periodic['totals'];

        // Strict Parity Assertions down to centavo
        $this->assertEquals($summary['gross_sales'], $totals['gross_sales']);
        $this->assertEquals($summary['net_sales'], $totals['net_sales']);
        $this->assertEquals($summary['total_discounts'], $totals['total_discounts']);
        $this->assertEquals($summary['orders_count'], $totals['orders_count']);
        $this->assertEquals($summary['tax_summary']['vatable_sales'], $totals['vatable_sales']);
        $this->assertEquals($summary['tax_summary']['vat_amount'], $totals['vat_amount']);
        $this->assertEquals($summary['tax_summary']['vat_exempt_sales'], $totals['vat_exempt_sales']);

        // CSV export parity
        $csv = $this->aggregationService->exportPeriodicCsv('day', null, null, $this->branch->id);
        $this->assertStringContainsString('"TOTAL / SUMMARY",3,1260.00,126.00,1134.00,562.50,67.50,504.00,378.00', $csv);
    }
}
