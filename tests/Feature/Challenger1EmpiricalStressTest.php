<?php

namespace Tests\Feature;

use App\Domains\Branches\Models\Branch;
use App\Domains\Identity\Models\User;
use App\Domains\Menu\Models\Meal;
use App\Domains\Menu\Models\MealCategory;
use App\Domains\PointOfSale\Models\Order;
use App\Domains\PointOfSale\Models\OrderItem;
use App\Domains\PointOfSale\Models\Payment;
use App\Domains\PointOfSale\Services\SalesAggregationService;
use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\PeakSalesHoursChart;
use App\Support\Enums\OrderStatus;
use App\Support\Enums\PaymentStatus;
use App\Support\Enums\StaffRole;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class Challenger1EmpiricalStressTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branchA;
    protected Branch $branchB;
    protected User $managerA;
    protected User $managerB;
    protected User $cashierA;
    protected User $cashierB;
    protected User $owner;
    protected MealCategory $categoryA;
    protected MealCategory $categoryB;
    protected Meal $mealA;
    protected Meal $mealB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Cache::flush();

        $this->branchA = Branch::firstOrCreate(['code' => 'BR-A'], ['name' => 'Branch Alpha', 'is_active' => true]);
        $this->branchB = Branch::firstOrCreate(['code' => 'BR-B'], ['name' => 'Branch Beta', 'is_active' => true]);

        $this->owner = User::firstOrCreate(['email' => 'owner@easypos.com'], [
            'name' => 'System Owner',
            'password' => Hash::make('password'),
            'emp_id' => 'HQ-001',
            'is_active' => true,
        ]);
        if (! $this->owner->hasRole(StaffRole::OWNER->value)) {
            $this->owner->assignRole(StaffRole::OWNER->value);
        }

        $this->managerA = User::create([
            'name' => 'Manager A',
            'email' => 'mgr_a@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'MGR-A-01',
            'branch_id' => $this->branchA->id,
            'is_active' => true,
        ]);
        $this->managerA->assignRole(StaffRole::BRANCH_MANAGER->value);

        $this->managerB = User::create([
            'name' => 'Manager B',
            'email' => 'mgr_b@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'MGR-B-01',
            'branch_id' => $this->branchB->id,
            'is_active' => true,
        ]);
        $this->managerB->assignRole(StaffRole::BRANCH_MANAGER->value);

        $this->cashierA = User::create([
            'name' => 'Cashier A',
            'email' => 'csh_a@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'CSH-A-01',
            'branch_id' => $this->branchA->id,
            'is_active' => true,
        ]);
        $this->cashierA->assignRole(StaffRole::CASHIER->value);

        $this->cashierB = User::create([
            'name' => 'Cashier B',
            'email' => 'csh_b@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'CSH-B-01',
            'branch_id' => $this->branchB->id,
            'is_active' => true,
        ]);
        $this->cashierB->assignRole(StaffRole::CASHIER->value);

        $this->categoryA = MealCategory::create([
            'name' => 'Alpha Specialties',
            'slug' => 'alpha-specialties',
            'is_active' => true,
        ]);

        $this->categoryB = MealCategory::create([
            'name' => 'Beta Secret Formula',
            'slug' => 'beta-secret-formula',
            'is_active' => true,
        ]);

        $this->mealA = Meal::create([
            'name' => 'Alpha Burger',
            'menu_code' => 'ALPH-01',
            'category_id' => $this->categoryA->id,
            'price' => 200.00,
            'is_available' => true,
            'is_active' => true,
        ]);

        $this->mealB = Meal::create([
            'name' => 'Beta Gourmet Steak',
            'menu_code' => 'BETA-01',
            'category_id' => $this->categoryB->id,
            'price' => 1500.00,
            'is_available' => true,
            'is_active' => true,
        ]);
    }

    /**
     * EMPIRICAL TEST 1: Service container singleton & in-memory runtimeCache deduplication.
     * Verifies if app(SalesAggregationService::class) returns a singleton instance.
     */
    public function test_service_container_singleton_deduplication(): void
    {
        $instance1 = app(SalesAggregationService::class);
        $instance2 = app(SalesAggregationService::class);

        $this->assertSame(
            $instance1,
            $instance2,
            'CRITICAL: SalesAggregationService must be bound as a singleton in the container, otherwise $runtimeCache is isolated per instance and does NOT deduplicate across Dashboard and PeakSalesHoursChart.'
        );
    }

    /**
     * EMPIRICAL TEST 2: Financial Calculation Invariants in computeSummary().
     * Tests exact VAT math, discounts, payments reconciliation, and consistency
     * between full summary and minimal summary (includeDetails: false).
     */
    public function test_financial_calculation_invariants_and_minimal_summary_parity(): void
    {
        $service = app(SalesAggregationService::class);

        // Order 1: Regular VATable sale (2x Alpha Burger = 400.00), paid cash (500 tendered, 100 change)
        $order1 = Order::create([
            'order_number' => 'ORD-INV-001',
            'branch_id' => $this->branchA->id,
            'cashier_id' => $this->cashierA->id,
            'order_type' => 'dine_in',
            'subtotal' => 400.00,
            'discount_amount' => 0.00,
            'total_amount' => 400.00,
            'tax_amount' => 42.86,
            'status' => OrderStatus::COMPLETED->value,
            'payment_status' => PaymentStatus::PAID->value,
            'created_at' => Carbon::parse('2026-11-12 12:00:00'),
        ]);
        OrderItem::create([
            'order_id' => $order1->id,
            'meal_id' => $this->mealA->id,
            'meal_name' => $this->mealA->name,
            'unit_price' => 200.00,
            'quantity' => 2,
            'subtotal' => 400.00,
        ]);
        Payment::create([
            'order_id' => $order1->id,
            'branch_id' => $this->branchA->id,
            'cashier_id' => $this->cashierA->id,
            'payment_method' => 'cash',
            'amount' => 400.00,
            'amount_tendered' => 500.00,
            'change_given' => 100.00,
            'status' => 'successful',
            'created_at' => Carbon::parse('2026-11-12 12:00:00'),
        ]);

        // Order 2: Discounted senior citizen sale (subtotal 600.00, discount 120.00, total 480.00), paid via digital QR
        $order2 = Order::create([
            'order_number' => 'ORD-INV-002',
            'branch_id' => $this->branchA->id,
            'cashier_id' => $this->cashierA->id,
            'order_type' => 'takeout',
            'subtotal' => 600.00,
            'discount_amount' => 120.00,
            'total_amount' => 480.00,
            'tax_amount' => 0.00,
            'status' => OrderStatus::COMPLETED->value,
            'payment_status' => PaymentStatus::PAID->value,
            'created_at' => Carbon::parse('2026-11-12 13:00:00'),
        ]);
        OrderItem::create([
            'order_id' => $order2->id,
            'meal_id' => $this->mealA->id,
            'meal_name' => $this->mealA->name,
            'unit_price' => 200.00,
            'quantity' => 3,
            'subtotal' => 600.00,
        ]);
        Payment::create([
            'order_id' => $order2->id,
            'branch_id' => $this->branchA->id,
            'cashier_id' => $this->cashierA->id,
            'payment_method' => 'qr_transfer',
            'amount' => 480.00,
            'amount_tendered' => 480.00,
            'change_given' => 0.00,
            'status' => 'successful',
            'created_at' => Carbon::parse('2026-11-12 13:00:00'),
        ]);

        $this->actingAs($this->owner);

        $fullSummary = $service->getSummary('2026-11-12', '2026-11-12', $this->branchA->id, null, false, true);
        $minSummary = $service->getSummary('2026-11-12', '2026-11-12', $this->branchA->id, null, false, false);

        // Invariant 1: Financial math verification
        $this->assertEquals(1000.00, $fullSummary['gross_sales']);
        $this->assertEquals(880.00, $fullSummary['net_sales']);
        $this->assertEquals(120.00, $fullSummary['total_discounts']);
        $this->assertEquals(2, $fullSummary['orders_count']);
        $this->assertEquals(440.00, $fullSummary['average_order_value']);

        // Invariant 2: Payments reconciliation
        $this->assertEquals(400.00, $fullSummary['cash_reconciliation']['net_cash_in_drawer']);
        $this->assertEquals(500.00, $fullSummary['cash_reconciliation']['total_cash_tendered']);
        $this->assertEquals(100.00, $fullSummary['cash_reconciliation']['total_change_given']);
        $this->assertEquals(480.00, $fullSummary['cash_reconciliation']['total_digital']);

        // Invariant 3: Minimal summary parity (core financial fields match exactly)
        $financialKeys = [
            'gross_sales', 'net_sales', 'total_discounts', 'orders_count',
            'average_order_value', 'cash_reconciliation', 'refunds_summary',
            'order_types_breakdown', 'payments_breakdown'
        ];
        foreach ($financialKeys as $key) {
            $this->assertEquals($fullSummary[$key], $minSummary[$key], "Field {$key} must match between full and minimal summary.");
        }
        $this->assertEquals($fullSummary['tax_summary'], $minSummary['tax_summary']);
    }

    /**
     * EMPIRICAL TEST 3: Branch Cache Isolation & Tampering Resistance in getCategoryRankings().
     * Tests whether getCategoryRankings() strictly enforces branch isolation when
     * branchId is null or tampered, and whether cache keys isolate Branch A and Branch B.
     */
    public function test_category_rankings_branch_isolation_and_tampering(): void
    {
        $service = app(SalesAggregationService::class);

        // Branch A order with Alpha Burger
        $orderA = Order::create([
            'order_number' => 'ORD-BR-A-01',
            'branch_id' => $this->branchA->id,
            'cashier_id' => $this->cashierA->id,
            'order_type' => 'dine_in',
            'subtotal' => 200.00,
            'discount_amount' => 0.00,
            'total_amount' => 200.00,
            'status' => OrderStatus::COMPLETED->value,
            'payment_status' => PaymentStatus::PAID->value,
            'created_at' => Carbon::parse('2026-11-12 12:00:00'),
        ]);
        OrderItem::create([
            'order_id' => $orderA->id,
            'meal_id' => $this->mealA->id,
            'meal_name' => $this->mealA->name,
            'unit_price' => 200.00,
            'quantity' => 1,
            'subtotal' => 200.00,
        ]);

        // Branch B order with Beta Secret Gourmet Steak
        $orderB = Order::create([
            'order_number' => 'ORD-BR-B-01',
            'branch_id' => $this->branchB->id,
            'cashier_id' => $this->cashierB->id,
            'order_type' => 'dine_in',
            'subtotal' => 1500.00,
            'discount_amount' => 0.00,
            'total_amount' => 1500.00,
            'status' => OrderStatus::COMPLETED->value,
            'payment_status' => PaymentStatus::PAID->value,
            'created_at' => Carbon::parse('2026-11-12 12:00:00'),
        ]);
        OrderItem::create([
            'order_id' => $orderB->id,
            'meal_id' => $this->mealB->id,
            'meal_name' => $this->mealB->name,
            'unit_price' => 1500.00,
            'quantity' => 1,
            'subtotal' => 1500.00,
        ]);

        // Act as Branch Manager A
        $this->actingAs($this->managerA);
        Cache::flush();

        // 1. Manager A passes branchId = null -> should be scoped to Branch A
        $categoriesNull = $service->getCategoryRankings('2026-11-12', '2026-11-12', null);
        $categoryNamesNull = array_column($categoriesNull, 'category_name');

        $this->assertNotContains(
            'Beta Secret Formula',
            $categoryNamesNull,
            'CRITICAL SECURITY VULNERABILITY: getCategoryRankings(branchId: null) leaked Branch B data to Branch A Manager!'
        );

        // 2. Manager A attempts to tamper with branchId = branchB->id -> should be forced to Branch A
        $categoriesTamper = $service->getCategoryRankings('2026-11-12', '2026-11-12', $this->branchB->id);
        $categoryNamesTamper = array_column($categoriesTamper, 'category_name');

        $this->assertNotContains(
            'Beta Secret Formula',
            $categoryNamesTamper,
            'CRITICAL SECURITY VULNERABILITY: getCategoryRankings(branchId: branchB->id) allowed Branch A Manager to query Branch B data!'
        );
    }

    /**
     * EMPIRICAL TEST 4: Zero Orders Edge Condition.
     * Verifies that SalesAggregationService handles empty periods without division by zero.
     */
    public function test_zero_orders_edge_conditions(): void
    {
        $service = app(SalesAggregationService::class);
        $this->actingAs($this->managerA);

        $summary = $service->getSummary('2025-01-01', '2025-01-01', $this->branchA->id, null, false);
        $this->assertSame(0, $summary['orders_count']);
        $this->assertSame(0.00, $summary['gross_sales']);
        $this->assertSame(0.00, $summary['net_sales']);
        $this->assertSame(0.00, $summary['average_order_value']);
        $this->assertSame([], $summary['top_selling_items']);
        $this->assertSame([], $summary['cashier_performance']);

        $categories = $service->getCategoryRankings('2025-01-01', '2025-01-01', $this->branchA->id);
        $this->assertSame([], $categories);

        $hourly = $service->getHourlySalesDistribution('2025-01-01', '2025-01-01', $this->branchA->id);
        $this->assertSame(0, $hourly['total_orders']);
        $this->assertSame(0.00, (float) $hourly['total_revenue']);
        $this->assertCount(24, $hourly['hours']);
    }

    /**
     * EMPIRICAL TEST 5: User Constraint Enforcement - File Lines Under 500.
     * Verifies that modified services remain strictly under 500 lines per user instructions.
     */
    public function test_files_remain_under_500_lines(): void
    {
        $serviceFile = app_path('Domains/PointOfSale/Services/SalesAggregationService.php');
        $lineCount = count(file($serviceFile));

        $this->assertLessThanOrEqual(
            500,
            $lineCount,
            "CRITICAL CONSTRAINT FAILURE: SalesAggregationService.php has {$lineCount} lines, exceeding the mandatory 500-line constraint. It must be decomposed into single-responsibility actions/traits."
        );
    }

    /**
     * EMPIRICAL TEST 6: Reporting and Concurrency Covering Indexes in Database Schema.
     */
    public function test_reporting_and_concurrency_covering_indexes_exist_in_database_schema(): void
    {
        $this->assertTrue(
            Schema::hasIndex('order_items', 'idx_order_items_reporting_covering'),
            'Failed asserting that order_items table has idx_order_items_reporting_covering index.'
        );

        $indexes = collect(Schema::getIndexes('order_items'));
        $covering = $indexes->firstWhere('name', 'idx_order_items_reporting_covering');
        $this->assertNotNull($covering, 'Covering index definition not found.');
        $this->assertSame(['order_id', 'meal_id', 'quantity', 'subtotal'], $covering['columns']);

        $migration = require database_path('migrations/2026_11_13_000001_add_reporting_covering_index_to_order_items.php');
        $migration->down();
        $this->assertFalse(Schema::hasIndex('order_items', 'idx_order_items_reporting_covering'));
        $migration->up();
        $this->assertTrue(Schema::hasIndex('order_items', 'idx_order_items_reporting_covering'));

        $this->assertTrue(
            Schema::hasIndex('orders', 'idx_orders_branch_reporting'),
            'Failed asserting that orders table has idx_orders_branch_reporting index.'
        );

        $this->assertTrue(
            Schema::hasIndex('payments', 'idx_payments_branch_reconciliation'),
            'Failed asserting that payments table has idx_payments_branch_reconciliation index.'
        );

        $this->assertTrue(
            Schema::hasIndex('kitchen_tickets', 'idx_kds_signature_covering'),
            'Failed asserting that kitchen_tickets table has idx_kds_signature_covering index.'
        );

        $this->assertTrue(
            Schema::hasIndex('branch_daily_sequences', 'uk_branch_date_seq_type'),
            'Failed asserting that branch_daily_sequences table has uk_branch_date_seq_type index.'
        );
    }

    /**
     * EMPIRICAL TEST 7: AUDIT.md Contract & Empirical Benchmark Validation.
     */
    public function test_audit_markdown_contract_and_empirical_benchmarks(): void
    {
        $auditFile = base_path('AUDIT.md');
        $this->assertFileExists($auditFile, 'AUDIT.md must exist in base path.');

        $lines = file($auditFile);
        $totalLines = count($lines);
        $this->assertLessThan(
            500,
            $totalLines,
            "CRITICAL CONSTRAINT: AUDIT.md must remain strictly under 500 lines. Current: {$totalLines} lines."
        );

        $content = file_get_contents($auditFile);

        // Required Section Header
        $this->assertStringContainsString('## SQL Audit & Query Benchmarks', $content);

        // Required Migration & Index references
        $this->assertStringContainsString('2026_11_13_000001_add_reporting_covering_index_to_order_items.php', $content);
        $this->assertStringContainsString('idx_order_items_reporting_covering', $content);
        $this->assertStringContainsString('idx_orders_branch_reporting', $content);
        $this->assertStringContainsString('idx_payments_branch_reconciliation', $content);
        $this->assertStringContainsString('idx_kds_signature_covering', $content);

        // 8 Core Query Benchmarks
        $coreQueries = [
            'Orders summary',
            'Order items best-sellers',
            'Hourly distribution',
            'KDS signature polling',
            'POS order progress badges',
            'Menu catalog pagination',
            'Daily sequence point-seek',
            'Payments cash reconciliation',
        ];

        foreach ($coreQueries as $queryName) {
            $this->assertStringContainsString($queryName, $content, "AUDIT.md must document benchmark for '{$queryName}'.");
        }

        // Empirical Latency Threshold
        $this->assertStringContainsString('<1.1 ms', $content);
    }
}
