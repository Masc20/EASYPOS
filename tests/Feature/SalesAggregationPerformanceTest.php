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
use App\Filament\Pages\SalesRecords;
use App\Filament\Widgets\PaymentMethodsDistributionChart;
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
use Livewire\Livewire;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

class SalesAggregationPerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branch1;
    protected Branch $branch2;
    protected User $owner;
    protected User $manager1;
    protected User $cashier1;
    protected User $cashier2;
    protected MealCategory $category;
    protected Meal $meal1;
    protected Meal $meal2;
    protected SalesAggregationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Cache::flush();

        $this->branch1 = Branch::firstOrCreate(['code' => 'B01'], [
            'name' => 'Branch Metro Manila',
            'is_active' => true,
        ]);

        $this->branch2 = Branch::firstOrCreate(['code' => 'B02'], [
            'name' => 'Branch Cebu Central',
            'is_active' => true,
        ]);

        $this->owner = User::firstOrCreate(['email' => 'owner@easypos.com'], [
            'name' => 'System Owner',
            'password' => Hash::make('password'),
            'emp_id' => 'HQ-ADM-001',
            'is_active' => true,
        ]);
        if (! $this->owner->hasRole(StaffRole::OWNER->value)) {
            $this->owner->assignRole(StaffRole::OWNER->value);
        }

        $this->manager1 = User::create([
            'name' => 'Manager Metro 1',
            'email' => 'm1_perf@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'B01-MGR-P01',
            'branch_id' => $this->branch1->id,
            'is_active' => true,
        ]);
        $this->manager1->assignRole(StaffRole::BRANCH_MANAGER->value);

        $this->cashier1 = User::create([
            'name' => 'Cashier Metro 1',
            'email' => 'c1_perf@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'B01-CSH-P01',
            'branch_id' => $this->branch1->id,
            'is_active' => true,
        ]);
        $this->cashier1->assignRole(StaffRole::CASHIER->value);

        $this->cashier2 = User::create([
            'name' => 'Cashier Cebu 2',
            'email' => 'c2_perf@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'B02-CSH-P02',
            'branch_id' => $this->branch2->id,
            'is_active' => true,
        ]);
        $this->cashier2->assignRole(StaffRole::CASHIER->value);

        $this->category = MealCategory::create([
            'name' => 'Main Courses',
            'slug' => 'main-courses',
            'is_active' => true,
        ]);

        $this->meal1 = Meal::create([
            'name' => 'Angus Steak Burger',
            'menu_code' => 'BUR-001',
            'category_id' => $this->category->id,
            'price' => 300.00,
            'is_available' => true,
            'is_active' => true,
        ]);

        $this->meal2 = Meal::create([
            'name' => 'Chicken Teriyaki Bowl',
            'menu_code' => 'BWL-001',
            'category_id' => $this->category->id,
            'price' => 250.00,
            'is_available' => true,
            'is_active' => true,
        ]);

        $this->service = app(SalesAggregationService::class);
    }

    /**
     * F6: Test streamOrdersCsv writes UTF-8 BOM, standard headers, and accurate formatted rows.
     */
    public function test_stream_orders_csv_emits_utf8_bom_headers_and_correct_totals(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-STR-001',
            'branch_id' => $this->branch1->id,
            'cashier_id' => $this->cashier1->id,
            'order_type' => 'dine_in',
            'table_number' => 'T-05',
            'subtotal' => 550.00,
            'discount_amount' => 50.00,
            'total_amount' => 500.00,
            'status' => OrderStatus::COMPLETED->value,
            'payment_status' => PaymentStatus::PAID->value,
            'created_at' => Carbon::parse('2026-11-10 12:30:00'),
        ]);

        Payment::create([
            'order_id' => $order->id,
            'branch_id' => $this->branch1->id,
            'cashier_id' => $this->cashier1->id,
            'payment_method' => 'cash',
            'amount' => 500.00,
            'amount_tendered' => 1000.00,
            'change_given' => 500.00,
            'status' => 'successful',
        ]);

        $stream = fopen('php://temp', 'r+');
        $this->service->streamOrdersCsv($stream, '2026-11-10', '2026-11-10', $this->branch1->id);
        rewind($stream);
        $content = stream_get_contents($stream);
        fclose($stream);

        // Verify UTF-8 BOM
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);

        // Verify CSV Headers
        $this->assertStringContainsString('Order #', $content);
        $this->assertStringContainsString('Date & Time', $content);
        $this->assertStringContainsString('Dining Option', $content);
        $this->assertStringContainsString('Gross Subtotal (PHP)', $content);
        $this->assertStringContainsString('Discount (PHP)', $content);
        $this->assertStringContainsString('Net Total (PHP)', $content);
        $this->assertStringContainsString('Payment Method', $content);
        $this->assertStringContainsString('Status', $content);

        // Verify formatted order line
        $this->assertStringContainsString('ORD-STR-001', $content);
        $this->assertStringContainsString($this->branch1->name, $content);
        $this->assertStringContainsString('Cashier Metro 1', $content);
        $this->assertStringContainsString('DINE IN', $content);
        $this->assertStringContainsString('T-05', $content);
        $this->assertStringContainsString('550.00', $content);
        $this->assertStringContainsString('50.00', $content);
        $this->assertStringContainsString('500.00', $content);
        $this->assertStringContainsString('Cash', $content);
        $this->assertStringContainsString('Completed', $content);
        $this->assertStringContainsString('Cashier Metro 1', $content);
        $this->assertStringContainsString('DINE IN', $content);
        $this->assertStringContainsString('T-05', $content);
        $this->assertStringContainsString('550.00', $content);
        $this->assertStringContainsString('50.00', $content);
        $this->assertStringContainsString('500.00', $content);
        $this->assertStringContainsString('Cash', $content);
        $this->assertStringContainsString('Completed', $content);
    }

    /**
     * F6: Test streamOrdersCsv maintains constant bounded memory (O(1) memory footprint).
     */
    public function test_stream_orders_csv_maintains_constant_bounded_memory(): void
    {
        // Seed 100 orders with payments
        $ordersData = [];
        $now = now();
        for ($i = 1; $i <= 100; $i++) {
            $ordersData[] = [
                'order_number' => sprintf('ORD-MEM-%04d', $i),
                'branch_id' => $this->branch1->id,
                'cashier_id' => $this->cashier1->id,
                'order_type' => $i % 2 === 0 ? 'dine_in' : 'takeout',
                'table_number' => 'T-' . ($i % 10 + 1),
                'subtotal' => 300.00,
                'discount_amount' => 0.00,
                'total_amount' => 300.00,
                'tax_amount' => 0.00,
                'status' => OrderStatus::COMPLETED->value,
                'payment_status' => PaymentStatus::PAID->value,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        Order::insert($ordersData);

        $insertedOrders = Order::where('branch_id', $this->branch1->id)->get(['id']);
        $paymentsData = [];
        foreach ($insertedOrders as $ord) {
            $paymentsData[] = [
                'order_id' => $ord->id,
                'branch_id' => $this->branch1->id,
                'cashier_id' => $this->cashier1->id,
                'payment_method' => 'cash',
                'amount' => 300.00,
                'amount_tendered' => 300.00,
                'change_given' => 0.00,
                'status' => 'successful',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        Payment::insert($paymentsData);

        $memoryBefore = memory_get_usage();

        $stream = fopen('php://temp', 'r+');
        $this->service->streamOrdersCsv($stream, null, null, $this->branch1->id);
        $memoryDuring = memory_get_usage();
        fclose($stream);

        $memoryDeltaMB = ($memoryDuring - $memoryBefore) / 1024 / 1024;
        $this->assertLessThan(10.0, $memoryDeltaMB, 'Streaming CSV must retain bounded O(1) memory delta under 10MB.');
    }

    /**
     * F4: Test query consolidation in getSummary executes <= 4 queries instead of 7+.
     */
    public function test_get_summary_query_consolidation_executes_at_most_four_queries(): void
    {
        // Order 1: Regular completed & paid
        $order1 = Order::create([
            'order_number' => 'ORD-QC-001',
            'branch_id' => $this->branch1->id,
            'cashier_id' => $this->cashier1->id,
            'order_type' => 'dine_in',
            'subtotal' => 600.00,
            'discount_amount' => 0.00,
            'total_amount' => 600.00,
            'status' => OrderStatus::COMPLETED->value,
            'payment_status' => PaymentStatus::PAID->value,
            'created_at' => now(),
        ]);
        OrderItem::create([
            'order_id' => $order1->id,
            'meal_id' => $this->meal1->id,
            'meal_name' => $this->meal1->name,
            'unit_price' => 300.00,
            'quantity' => 2,
            'subtotal' => 600.00,
        ]);
        Payment::create([
            'order_id' => $order1->id,
            'branch_id' => $this->branch1->id,
            'cashier_id' => $this->cashier1->id,
            'payment_method' => 'cash',
            'amount' => 600.00,
            'amount_tendered' => 1000.00,
            'change_given' => 400.00,
            'status' => 'successful',
        ]);

        // Order 2: Discounted senior citizen (VAT exempt)
        $order2 = Order::create([
            'order_number' => 'ORD-QC-002',
            'branch_id' => $this->branch1->id,
            'cashier_id' => $this->cashier1->id,
            'order_type' => 'takeout',
            'subtotal' => 500.00,
            'discount_amount' => 100.00,
            'total_amount' => 400.00,
            'status' => OrderStatus::COMPLETED->value,
            'payment_status' => PaymentStatus::PAID->value,
            'created_at' => now(),
        ]);
        OrderItem::create([
            'order_id' => $order2->id,
            'meal_id' => $this->meal2->id,
            'meal_name' => $this->meal2->name,
            'unit_price' => 250.00,
            'quantity' => 2,
            'subtotal' => 500.00,
        ]);
        Payment::create([
            'order_id' => $order2->id,
            'branch_id' => $this->branch1->id,
            'cashier_id' => $this->cashier1->id,
            'payment_method' => 'qr_transfer',
            'amount' => 400.00,
            'amount_tendered' => 400.00,
            'change_given' => 0.00,
            'status' => 'successful',
        ]);

        // Order 3: Refunded order
        Order::create([
            'order_number' => 'ORD-QC-003',
            'branch_id' => $this->branch1->id,
            'cashier_id' => $this->cashier1->id,
            'order_type' => 'dine_in',
            'subtotal' => 250.00,
            'discount_amount' => 0.00,
            'total_amount' => 250.00,
            'status' => OrderStatus::CANCELLED->value,
            'payment_status' => PaymentStatus::REFUNDED->value,
            'created_at' => now(),
        ]);

        DB::flushQueryLog();
        DB::enableQueryLog();

        // Call computeSummary directly (useCache = false to count genuine SQL queries)
        $summary = $this->service->getSummary(null, null, $this->branch1->id, null, false);

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // Assert query count is consolidated to <= 4 queries
        $this->assertLessThanOrEqual(4, count($queries), sprintf(
            'Expected getSummary() to execute <= 4 queries, but executed %d queries.',
            count($queries)
        ));

        // Verify financial calculations are 100% mathematically accurate
        $this->assertEquals(1100.00, $summary['gross_sales']);
        $this->assertEquals(1000.00, $summary['net_sales']);
        $this->assertEquals(100.00, $summary['total_discounts']);
        $this->assertEquals(2, $summary['orders_count']);
        $this->assertEquals(500.00, $summary['average_order_value']);

        // Check refunds
        $this->assertEquals(250.00, $summary['refunds_summary']['refunded_amount']);
        $this->assertEquals(1, $summary['refunds_summary']['refunded_count']);

        // Check order types
        $this->assertEquals(1, $summary['order_types_breakdown']['dine_in']);
        $this->assertEquals(1, $summary['order_types_breakdown']['takeout']);

        // Check payments reconciliation
        $this->assertEquals(600.00, $summary['cash_reconciliation']['net_cash_in_drawer']);
        $this->assertEquals(1000.00, $summary['cash_reconciliation']['total_cash_tendered']);
        $this->assertEquals(400.00, $summary['cash_reconciliation']['total_change_given']);
        $this->assertEquals(400.00, $summary['cash_reconciliation']['total_digital']);
    }

    /**
     * F5: Test branch-scoped short-lived caching and isolation guardrails.
     */
    public function test_branch_scoped_cache_isolation_and_tenancy_guardrails(): void
    {
        // Branch 1 Order
        Order::create([
            'order_number' => 'B01-ORD-01',
            'branch_id' => $this->branch1->id,
            'cashier_id' => $this->cashier1->id,
            'order_type' => 'dine_in',
            'subtotal' => 500.00,
            'discount_amount' => 0.00,
            'total_amount' => 500.00,
            'status' => OrderStatus::COMPLETED->value,
            'payment_status' => PaymentStatus::PAID->value,
            'created_at' => now(),
        ]);

        // Branch 2 Order
        Order::create([
            'order_number' => 'B02-ORD-01',
            'branch_id' => $this->branch2->id,
            'cashier_id' => $this->cashier2->id,
            'order_type' => 'dine_in',
            'subtotal' => 1500.00,
            'discount_amount' => 0.00,
            'total_amount' => 1500.00,
            'status' => OrderStatus::COMPLETED->value,
            'payment_status' => PaymentStatus::PAID->value,
            'created_at' => now(),
        ]);

        // 1. Acting as Branch 1 Manager
        $this->actingAs($this->manager1);

        // Branch manager cannot query global or other branch
        $b1Summary = $this->service->getSummary(null, null, $this->branch2->id); // Tampered branchId = 2
        $this->assertEquals(500.00, $b1Summary['net_sales'], 'Branch Manager must be forced to Branch 1 data');

        $b1Key = $this->service->buildSummaryCacheKey(null, null, $this->manager1->branch_id, null);
        $this->assertTrue(Cache::has($b1Key), 'Branch 1 cache key must exist');

        // Verify Branch 2 cache key is NOT populated by Branch 1 Manager
        $b2Key = $this->service->buildSummaryCacheKey(null, null, $this->branch2->id, null);
        $this->assertFalse(Cache::has($b2Key), 'Branch 2 cache key must not be generated by Branch 1 Manager');

        // 2. Acting as Owner
        $this->actingAs($this->owner);

        // Owner can query Branch 2 specifically
        $b2Summary = $this->service->getSummary(null, null, $this->branch2->id);
        $this->assertEquals(1500.00, $b2Summary['net_sales']);
        $this->assertTrue(Cache::has($b2Key), 'Branch 2 cache key should now exist');

        // Owner can query Global (null branchId)
        $globalSummary = $this->service->getSummary(null, null, null);
        $this->assertEquals(2000.00, $globalSummary['net_sales'], 'Global net sales should be sum of both branches');
        $globalKey = $this->service->buildSummaryCacheKey(null, null, null, null);
        $this->assertTrue(Cache::has($globalKey), 'Global cache key should exist for owner');

        // Verify all 3 cache keys hold distinct isolated data
        $cachedB1 = Cache::get($b1Key);
        $cachedB2 = Cache::get($b2Key);
        $cachedGlobal = Cache::get($globalKey);

        $this->assertEquals(500.00, $cachedB1['net_sales']);
        $this->assertEquals(1500.00, $cachedB2['net_sales']);
        $this->assertEquals(2000.00, $cachedGlobal['net_sales']);
    }

    /**
     * F4: Test widget deduplication avoids duplicate database queries.
     */
    public function test_widget_deduplication_avoids_redundant_queries(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-WID-001',
            'branch_id' => $this->branch1->id,
            'cashier_id' => $this->cashier1->id,
            'order_type' => 'dine_in',
            'subtotal' => 800.00,
            'discount_amount' => 0.00,
            'total_amount' => 800.00,
            'status' => OrderStatus::COMPLETED->value,
            'payment_status' => PaymentStatus::PAID->value,
            'created_at' => Carbon::now()->setTime(12, 15),
        ]);
        Payment::create([
            'order_id' => $order->id,
            'branch_id' => $this->branch1->id,
            'cashier_id' => $this->cashier1->id,
            'payment_method' => 'cash',
            'amount' => 800.00,
            'amount_tendered' => 800.00,
            'change_given' => 0.00,
            'status' => 'successful',
        ]);

        $this->actingAs($this->owner);
        Cache::flush();

        // 1. First call to getSummary() populates cache
        $summary = $this->service->getSummary(null, null, $this->branch1->id);
        $this->assertNotEmpty($summary['payments_breakdown']);

        // 2. Subsequent call to getPaymentMethodDistribution() hits cache with 0 queries
        DB::flushQueryLog();
        DB::enableQueryLog();

        $dist = $this->service->getPaymentMethodDistribution(null, null, $this->branch1->id);

        $paymentQueries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertCount(0, $paymentQueries, 'getPaymentMethodDistribution() must reuse cached getSummary data with 0 queries.');
        $this->assertEquals(['Cash'], $dist['labels']);
        $this->assertEquals([800.00], $dist['values']);

        // 3. Hourly distribution widget reuse
        $this->service->getHourlySalesDistribution(null, null, $this->branch1->id);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $hourlyDist2 = $this->service->getHourlySalesDistribution(null, null, $this->branch1->id);

        $hourlyQueries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertCount(0, $hourlyQueries, 'Hourly distribution call must hit 60s cache with 0 queries.');
        $this->assertEquals(1, $hourlyDist2['total_orders']);
    }

    /**
     * F6 & Multi-tenancy: Test SalesRecords exportCsv enforces branch tenancy.
     */
    public function test_sales_records_stream_download_enforces_branch_scoping(): void
    {
        Order::create([
            'order_number' => 'ORD-B1-STREAM',
            'branch_id' => $this->branch1->id,
            'cashier_id' => $this->cashier1->id,
            'order_type' => 'dine_in',
            'subtotal' => 100.00,
            'total_amount' => 100.00,
            'status' => OrderStatus::COMPLETED->value,
            'payment_status' => PaymentStatus::PAID->value,
            'created_at' => now(),
        ]);

        Order::create([
            'order_number' => 'ORD-B2-STREAM',
            'branch_id' => $this->branch2->id,
            'cashier_id' => $this->cashier2->id,
            'order_type' => 'dine_in',
            'subtotal' => 900.00,
            'total_amount' => 900.00,
            'status' => OrderStatus::COMPLETED->value,
            'payment_status' => PaymentStatus::PAID->value,
            'created_at' => now(),
        ]);

        $this->actingAs($this->manager1);

        // Branch 1 Manager renders SalesRecords page with tampered branchId = 2
        $component = Livewire::test(SalesRecords::class, ['branchId' => $this->branch2->id]);
        $response = $component->instance()->exportCsv($this->service);

        $this->assertInstanceOf(StreamedResponse::class, $response);
        $this->assertEquals('text/csv; charset=UTF-8', $response->headers->get('Content-Type'));

        // Capture streamed response output
        ob_start();
        $response->sendContent();
        $csvOutput = ob_get_clean();

        // Must include Branch 1 order
        $this->assertStringContainsString('ORD-B1-STREAM', $csvOutput);
        // Must NEVER leak Branch 2 order
        $this->assertStringNotContainsString('ORD-B2-STREAM', $csvOutput);
    }
}
