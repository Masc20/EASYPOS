<?php

namespace Tests\Feature\Adversarial;

use App\Domains\Branches\Models\Branch;
use App\Domains\Identity\Models\User;
use App\Domains\Menu\Models\Meal;
use App\Domains\Menu\Models\MealCategory;
use App\Domains\PointOfSale\Models\Order;
use App\Domains\PointOfSale\Models\OrderItem;
use App\Domains\PointOfSale\Models\Payment;
use App\Domains\PointOfSale\Services\SalesAggregationService;
use App\Filament\Pages\SalesRecords;
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

class AdversarialSalesAggregationStressTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branchA;
    protected Branch $branchB;
    protected User $owner;
    protected User $managerA;
    protected User $managerB;
    protected User $cashierA;
    protected User $cashierB;
    protected MealCategory $category1;
    protected MealCategory $category2;
    protected Meal $meal1;
    protected Meal $meal2;
    protected SalesAggregationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Cache::flush();

        $this->branchA = Branch::firstOrCreate(['code' => 'B-ADV1'], [
            'name' => 'Branch Parañaque ₱',
            'is_active' => true,
        ]);

        $this->branchB = Branch::firstOrCreate(['code' => 'B-ADV2'], [
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

        $this->managerA = User::create([
            'name' => 'Manager Alpha, Jr.',
            'email' => 'mgr_a_stress@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'B01-MGR-S01',
            'branch_id' => $this->branchA->id,
            'is_active' => true,
        ]);
        $this->managerA->assignRole(StaffRole::BRANCH_MANAGER->value);

        $this->managerB = User::create([
            'name' => 'Manager Beta',
            'email' => 'mgr_b_stress@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'B02-MGR-S02',
            'branch_id' => $this->branchB->id,
            'is_active' => true,
        ]);
        $this->managerB->assignRole(StaffRole::BRANCH_MANAGER->value);

        $this->cashierA = User::create([
            'name' => 'Niño Cashier "The Pro"',
            'email' => 'c1_stress@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'B01-CSH-S01',
            'branch_id' => $this->branchA->id,
            'is_active' => true,
        ]);
        $this->cashierA->assignRole(StaffRole::CASHIER->value);

        $this->cashierB = User::create([
            'name' => 'Cashier Cebu 2',
            'email' => 'c2_stress@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'B02-CSH-S02',
            'branch_id' => $this->branchB->id,
            'is_active' => true,
        ]);
        $this->cashierB->assignRole(StaffRole::CASHIER->value);

        $this->category1 = MealCategory::create([
            'name' => 'Specialties & Grills',
            'slug' => 'specialties-grills',
            'is_active' => true,
        ]);

        $this->category2 = MealCategory::create([
            'name' => 'Beverages & Desserts',
            'slug' => 'beverages-desserts',
            'is_active' => true,
        ]);

        $this->meal1 = Meal::create([
            'name' => 'Jalapeño & Angus Burger ₱',
            'menu_code' => 'BUR-ADV',
            'category_id' => $this->category1->id,
            'price' => 350.00,
            'is_available' => true,
            'is_active' => true,
        ]);

        $this->meal2 = Meal::create([
            'name' => 'Iced "Brewed" Coffee, Large',
            'menu_code' => 'DRK-ADV',
            'category_id' => $this->category2->id,
            'price' => 120.00,
            'is_available' => true,
            'is_active' => true,
        ]);

        $this->service = app(SalesAggregationService::class);
    }

    /**
     * EMPIRICAL CHALLENGE 1: Constant memory streaming under 1,200+ orders.
     * Peak memory delta must remain strictly bounded (O(1) memory < 20 MB).
     */
    public function test_csv_streaming_constant_memory_bounds_under_1200_orders(): void
    {
        // Generate 1,200 orders across 3 chunks (chunk size = 500)
        $batchCount = 1200;
        $now = now();
        $orders = [];

        for ($i = 1; $i <= $batchCount; $i++) {
            $orders[] = [
                'order_number' => sprintf('STRESS-%05d', $i),
                'branch_id' => $this->branchA->id,
                'cashier_id' => $this->cashierA->id,
                'order_type' => $i % 2 === 0 ? 'dine_in' : 'takeout',
                'table_number' => 'T-' . ($i % 20 + 1),
                'subtotal' => 350.00,
                'discount_amount' => $i % 5 === 0 ? 50.00 : 0.00,
                'total_amount' => $i % 5 === 0 ? 300.00 : 350.00,
                'tax_amount' => 0.00,
                'status' => OrderStatus::COMPLETED->value,
                'payment_status' => PaymentStatus::PAID->value,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($orders, 400) as $chunk) {
            Order::insert($chunk);
        }

        $allOrders = Order::where('branch_id', $this->branchA->id)->get(['id']);
        $payments = [];
        foreach ($allOrders as $ord) {
            $payments[] = [
                'order_id' => $ord->id,
                'branch_id' => $this->branchA->id,
                'cashier_id' => $this->cashierA->id,
                'payment_method' => 'cash',
                'amount' => 350.00,
                'amount_tendered' => 400.00,
                'change_given' => 50.00,
                'status' => 'successful',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        foreach (array_chunk($payments, 400) as $chunk) {
            Payment::insert($chunk);
        }

        $this->assertEquals($batchCount, Order::where('branch_id', $this->branchA->id)->count());

        // Measure memory before streaming
        gc_collect_cycles();
        $memBefore = memory_get_usage(false);
        $peakBefore = memory_get_peak_usage(false);

        // Stream output to a temporary file
        $tempHandle = tmpfile();
        $this->assertIsResource($tempHandle);

        $this->service->streamOrdersCsv($tempHandle, null, null, $this->branchA->id);

        $memAfter = memory_get_usage(false);
        $peakAfter = memory_get_peak_usage(false);

        rewind($tempHandle);
        $lineCount = 0;
        while (! feof($tempHandle)) {
            $line = fgets($tempHandle);
            if ($line !== false && trim($line) !== '') {
                $lineCount++;
            }
        }
        fclose($tempHandle);

        // 1 header row + 1200 data rows = 1201 rows
        $this->assertEquals(1201, $lineCount, 'All 1,200 orders plus CSV header must be streamed.');

        $peakDeltaMB = ($peakAfter - $peakBefore) / (1024 * 1024);
        $memDeltaMB = ($memAfter - $memBefore) / (1024 * 1024);

        // Memory delta must stay strictly under 20 MB (per DISPATCH.md criteria)
        $this->assertLessThan(20.0, $peakDeltaMB, sprintf(
            'Peak memory delta must remain bounded under 20 MB. Observed: %.2f MB',
            $peakDeltaMB
        ));
        $this->assertLessThan(10.0, $memDeltaMB, sprintf(
            'Net memory delta must remain bounded under 10 MB. Observed: %.2f MB',
            $memDeltaMB
        ));
    }

    /**
     * EMPIRICAL CHALLENGE 2: UTF-8 BOM, multi-byte Philippine characters, special symbols, and CSV escapes.
     */
    public function test_csv_streaming_utf8_bom_and_special_character_preservation(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-PHIL-ñ-001',
            'branch_id' => $this->branchA->id,
            'cashier_id' => $this->cashierA->id,
            'order_type' => 'dine_in',
            'table_number' => 'Table #3, "VIP" Section',
            'subtotal' => 1250.75,
            'discount_amount' => 125.00,
            'total_amount' => 1125.75,
            'status' => OrderStatus::COMPLETED->value,
            'payment_status' => PaymentStatus::PAID->value,
            'created_at' => Carbon::parse('2026-11-10 13:45:00'),
        ]);

        Payment::create([
            'order_id' => $order->id,
            'branch_id' => $this->branchA->id,
            'cashier_id' => $this->cashierA->id,
            'payment_method' => 'qr_transfer',
            'amount' => 1125.75,
            'amount_tendered' => 1125.75,
            'change_given' => 0.00,
            'status' => 'successful',
        ]);

        $stream = fopen('php://temp', 'r+');
        $this->service->streamOrdersCsv($stream, null, null, $this->branchA->id);
        rewind($stream);
        $rawContent = stream_get_contents($stream);
        fclose($stream);

        // 1. Verify UTF-8 BOM (\xEF\xBB\xBF) is at byte offset 0
        $this->assertStringStartsWith("\xEF\xBB\xBF", $rawContent, 'UTF-8 BOM must be at byte 0.');

        // Strip BOM and parse CSV lines
        $csvWithoutBom = substr($rawContent, 3);
        $lines = explode("\n", trim($csvWithoutBom));
        $this->assertCount(2, $lines);

        $headers = str_getcsv($lines[0]);
        $row = str_getcsv($lines[1]);

        $this->assertEquals('Order #', $headers[0]);
        $this->assertEquals('ORD-PHIL-ñ-001', $row[0]);
        $this->assertEquals('2026-11-10 13:45:00', $row[1]);
        // Branch name with special characters (Parañaque, ₱)
        $this->assertEquals('Branch Parañaque ₱', $row[2]);
        // Cashier with quotes and ñ (Niño Cashier "The Pro")
        $this->assertEquals('Niño Cashier "The Pro"', $row[3]);
        $this->assertEquals('DINE IN', $row[4]);
        // Table number with commas and quotes
        $this->assertEquals('Table #3, "VIP" Section', $row[5]);
        $this->assertEquals('1250.75', $row[6]);
        $this->assertEquals('125.00', $row[7]);
        $this->assertEquals('1125.75', $row[8]);
        $this->assertEquals('Qr Transfer', $row[9]);
        $this->assertEquals('Completed', $row[10]);
    }

    /**
     * EMPIRICAL CHALLENGE 3: Query count consolidation bounds.
     * Must never exceed 4 database queries regardless of data volume, filters, or cashier/category count.
     */
    public function test_get_summary_query_count_strictly_bounded_to_at_most_four_queries(): void
    {
        // Scenario A: Zero orders in database -> must execute <= 4 queries (actually 2)
        DB::flushQueryLog();
        DB::enableQueryLog();

        $emptySummary = $this->service->getSummary('2026-11-01', '2026-11-01', $this->branchA->id, null, false);

        $queriesEmpty = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(4, count($queriesEmpty), 'Empty results query count must be <= 4');
        $this->assertEquals(0, $emptySummary['orders_count']);

        // Scenario B: Dense dataset with multiple cashiers, categories, payment methods, and cancelled/refunded orders
        $cashiers = [$this->cashierA, $this->cashierB];
        $paymentMethods = ['cash', 'qr_transfer', 'credit_card'];

        for ($i = 1; $i <= 50; $i++) {
            $isRefund = $i % 10 === 0;
            $cashier = $cashiers[$i % 2];
            $ord = Order::create([
                'order_number' => sprintf('BOUND-%03d', $i),
                'branch_id' => $this->branchA->id,
                'cashier_id' => $cashier->id,
                'order_type' => $i % 2 === 0 ? 'dine_in' : 'takeout',
                'subtotal' => 500.00,
                'discount_amount' => $i % 4 === 0 ? 50.00 : 0.00,
                'total_amount' => $i % 4 === 0 ? 450.00 : 500.00,
                'status' => $isRefund ? OrderStatus::CANCELLED->value : OrderStatus::COMPLETED->value,
                'payment_status' => $isRefund ? PaymentStatus::REFUNDED->value : PaymentStatus::PAID->value,
                'created_at' => now(),
            ]);

            OrderItem::create([
                'order_id' => $ord->id,
                'meal_id' => $i % 2 === 0 ? $this->meal1->id : $this->meal2->id,
                'meal_name' => $i % 2 === 0 ? $this->meal1->name : $this->meal2->name,
                'unit_price' => 250.00,
                'quantity' => 2,
                'subtotal' => 500.00,
            ]);

            Payment::create([
                'order_id' => $ord->id,
                'branch_id' => $this->branchA->id,
                'cashier_id' => $cashier->id,
                'payment_method' => $paymentMethods[$i % 3],
                'amount' => $ord->total_amount,
                'amount_tendered' => $ord->total_amount,
                'change_given' => 0.00,
                'status' => 'successful',
            ]);
        }

        // Measure query count on dense data (useCache = false)
        DB::flushQueryLog();
        DB::enableQueryLog();

        $summary = $this->service->getSummary(null, null, $this->branchA->id, null, false);

        $queriesDense = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(4, count($queriesDense), sprintf(
            'Expected getSummary() to execute at most 4 queries on dense dataset, but executed %d queries.',
            count($queriesDense)
        ));

        // Verify mathematical accuracy
        $this->assertEquals(45, $summary['orders_count']); // 50 total - 5 refunded = 45 active
        $this->assertNotEmpty($summary['top_selling_items']);
        $this->assertNotEmpty($summary['cashier_performance']);
        $this->assertNotEmpty($summary['payments_breakdown']);
    }

    /**
     * EMPIRICAL CHALLENGE 4: Branch cache isolation, tampering & poisoning resistance.
     */
    public function test_branch_cache_isolation_and_tampering_resistance(): void
    {
        // Branch A sales: 1,000 PHP
        Order::create([
            'order_number' => 'ORD-BRANCH-A',
            'branch_id' => $this->branchA->id,
            'cashier_id' => $this->cashierA->id,
            'order_type' => 'dine_in',
            'subtotal' => 1000.00,
            'discount_amount' => 0.00,
            'total_amount' => 1000.00,
            'status' => OrderStatus::COMPLETED->value,
            'payment_status' => PaymentStatus::PAID->value,
            'created_at' => now(),
        ]);

        // Branch B sales: 90,000 PHP (confidential branch data)
        Order::create([
            'order_number' => 'ORD-BRANCH-B',
            'branch_id' => $this->branchB->id,
            'cashier_id' => $this->cashierB->id,
            'order_type' => 'dine_in',
            'subtotal' => 90000.00,
            'discount_amount' => 0.00,
            'total_amount' => 90000.00,
            'status' => OrderStatus::COMPLETED->value,
            'payment_status' => PaymentStatus::PAID->value,
            'created_at' => now(),
        ]);

        // Authenticate as Branch A Manager
        $this->actingAs($this->managerA);
        Cache::flush();

        // 1. Manager A tampers with branchId = Branch B
        $summaryTamperB = $this->service->getSummary(null, null, $this->branchB->id);
        $this->assertEquals(1000.00, $summaryTamperB['net_sales'], 'Manager A must be strictly scoped to Branch A sales.');

        // 2. Manager A tampers with branchId = null (attempt to poison global cache)
        $summaryTamperGlobal = $this->service->getSummary(null, null, null);
        $this->assertEquals(1000.00, $summaryTamperGlobal['net_sales'], 'Manager A passing null branchId must still resolve to Branch A.');

        // 3. Inspect generated cache keys
        $branchAKey = $this->service->buildSummaryCacheKey(null, null, $this->branchA->id, null);
        $branchBKey = $this->service->buildSummaryCacheKey(null, null, $this->branchB->id, null);
        $globalKey = $this->service->buildSummaryCacheKey(null, null, null, null);

        $this->assertTrue(Cache::has($branchAKey), 'Branch A cache key must be created.');
        $this->assertFalse(Cache::has($branchBKey), 'Branch B cache key must NOT be created or poisoned by Branch A Manager.');
        $this->assertFalse(Cache::has($globalKey), 'Global cache key must NOT be created or poisoned by Branch A Manager.');

        // 4. Test hourly cache key isolation under tampering
        $this->service->getHourlySalesDistribution(null, null, $this->branchB->id);
        $this->service->getHourlySalesDistribution(null, null, null);

        $hourlyAKey = $this->service->buildHourlyCacheKey(null, null, $this->branchA->id, null);
        $hourlyBKey = $this->service->buildHourlyCacheKey(null, null, $this->branchB->id, null);
        $hourlyGlobalKey = $this->service->buildHourlyCacheKey(null, null, null, null);

        $this->assertTrue(Cache::has($hourlyAKey));
        $this->assertFalse(Cache::has($hourlyBKey));
        $this->assertFalse(Cache::has($hourlyGlobalKey));

        // 5. Test best-sellers cache key isolation under tampering
        $this->service->getBestSellingRankings(null, null, $this->branchB->id);
        $this->service->getBestSellingRankings(null, null, null);

        $sellersAKey = $this->service->buildBestSellersCacheKey(null, null, $this->branchA->id, null, null, 10, 'quantity');
        $sellersBKey = $this->service->buildBestSellersCacheKey(null, null, $this->branchB->id, null, null, 10, 'quantity');
        $sellersGlobalKey = $this->service->buildBestSellersCacheKey(null, null, null, null, null, 10, 'quantity');

        $this->assertTrue(Cache::has($sellersAKey));
        $this->assertFalse(Cache::has($sellersBKey));
        $this->assertFalse(Cache::has($sellersGlobalKey));

        // 6. Pre-populate Branch B cache key (as Owner or Branch B Manager)
        $this->actingAs($this->managerB);
        $summaryB = $this->service->getSummary(null, null, $this->branchB->id);
        $this->assertEquals(90000.00, $summaryB['net_sales']);
        $this->assertTrue(Cache::has($branchBKey));

        // Switch back to Manager A and verify Manager A STILL cannot access Branch B data
        $this->actingAs($this->managerA);
        $summaryAAgain = $this->service->getSummary(null, null, $this->branchB->id);
        $this->assertEquals(1000.00, $summaryAAgain['net_sales'], 'Cached Branch B data must NEVER leak to Branch A Manager.');
    }

    /**
     * EMPIRICAL CHALLENGE 5: SalesRecords exportCsv Livewire action stream branch isolation.
     */
    public function test_sales_records_stream_download_tampered_input_enforces_branch_scoping(): void
    {
        Order::create([
            'order_number' => 'ORD-MGR-A-STREAM',
            'branch_id' => $this->branchA->id,
            'cashier_id' => $this->cashierA->id,
            'order_type' => 'dine_in',
            'subtotal' => 350.00,
            'total_amount' => 350.00,
            'status' => OrderStatus::COMPLETED->value,
            'payment_status' => PaymentStatus::PAID->value,
            'created_at' => now(),
        ]);

        Order::create([
            'order_number' => 'ORD-MGR-B-STREAM-SECRET',
            'branch_id' => $this->branchB->id,
            'cashier_id' => $this->cashierB->id,
            'order_type' => 'dine_in',
            'subtotal' => 9999.00,
            'total_amount' => 9999.00,
            'status' => OrderStatus::COMPLETED->value,
            'payment_status' => PaymentStatus::PAID->value,
            'created_at' => now(),
        ]);

        $this->actingAs($this->managerA);

        // Attempt tamper by setting component branchId to Branch B
        $component = Livewire::test(SalesRecords::class, ['branchId' => $this->branchB->id]);
        $response = $component->instance()->exportCsv($this->service);

        $this->assertInstanceOf(StreamedResponse::class, $response);

        ob_start();
        $response->sendContent();
        $csvOutput = ob_get_clean();

        $this->assertStringContainsString('ORD-MGR-A-STREAM', $csvOutput);
        $this->assertStringNotContainsString('ORD-MGR-B-STREAM-SECRET', $csvOutput);
        $this->assertStringNotContainsString('9999.00', $csvOutput);
    }

    /**
     * EMPIRICAL CHALLENGE 6: Adversarial inspection of getCategoryRankings and getPeriodicReport direct service calls.
     */
    public function test_category_rankings_and_periodic_report_branch_tenancy_guardrails(): void
    {
        // Branch A order
        $orderA = Order::create([
            'order_number' => 'ORD-CAT-A',
            'branch_id' => $this->branchA->id,
            'cashier_id' => $this->cashierA->id,
            'order_type' => 'dine_in',
            'subtotal' => 350.00,
            'total_amount' => 350.00,
            'status' => OrderStatus::COMPLETED->value,
            'payment_status' => PaymentStatus::PAID->value,
            'created_at' => now(),
        ]);
        OrderItem::create([
            'order_id' => $orderA->id,
            'meal_id' => $this->meal1->id,
            'meal_name' => $this->meal1->name,
            'unit_price' => 350.00,
            'quantity' => 1,
            'subtotal' => 350.00,
        ]);

        // Branch B order
        $orderB = Order::create([
            'order_number' => 'ORD-CAT-B',
            'branch_id' => $this->branchB->id,
            'cashier_id' => $this->cashierB->id,
            'order_type' => 'dine_in',
            'subtotal' => 120.00,
            'total_amount' => 120.00,
            'status' => OrderStatus::COMPLETED->value,
            'payment_status' => PaymentStatus::PAID->value,
            'created_at' => now(),
        ]);
        OrderItem::create([
            'order_id' => $orderB->id,
            'meal_id' => $this->meal2->id,
            'meal_name' => $this->meal2->name,
            'unit_price' => 120.00,
            'quantity' => 1,
            'subtotal' => 120.00,
        ]);

        $this->actingAs($this->managerA);

        // 1. Livewire SalesRecords page isolation (passes effectiveBranchId to internal calls)
        $component = Livewire::test(SalesRecords::class, ['branchId' => $this->branchB->id]);
        $component->assertOk();

        // 2. Direct service call vulnerability test:
        // In getCategoryRankings(), line 616 computes $effectiveBranchId = $this->resolveBranchScope($branchId);
        // BUT line 638 uses `if ($branchId) { $query->where('orders.branch_id', $branchId); }`
        // instead of `if ($effectiveBranchId)`.
        
        // When Manager A passes branchId = null:
        $nullBranchCategories = $this->service->getCategoryRankings(null, null, null);
        $nullBranchNames = array_column($nullBranchCategories, 'category_name');
        
        // Because $branchId is null, line 638 is skipped and queries all branches,
        // leaking Branch B's category 'Beverages & Desserts' to Manager A!
        $this->assertNotContains(
            'Beverages & Desserts',
            $nullBranchNames,
            'Branch A Manager querying with null branch must not receive Branch B categories.'
        );

        // When Manager A passes branchId = Branch B:
        $tamperedCategories = $this->service->getCategoryRankings(null, null, $this->branchB->id);
        $tamperedNames = array_column($tamperedCategories, 'category_name');
        $this->assertNotContains(
            'Beverages & Desserts',
            $tamperedNames,
            'Branch A Manager querying with Branch B branchId must not receive Branch B categories.'
        );
        
        // 3. Direct service call guardrail test for getPeriodicReport():
        // When Manager A attempts to query Branch B periodic report, resolveBranchScope() scopes to Branch A (350.00).
        $periodicB = $this->service->getPeriodicReport('day', null, null, $this->branchB->id);
        $this->assertEquals(
            350.00,
            $periodicB['totals']['net_sales'],
            'Branch A Manager querying with Branch B ID must be scoped to Branch A totals.'
        );
    }

    /**
     * EMPIRICAL CHALLENGE 7: Boundary conditions, empty datasets, and invalid filter robustness.
     */
    public function test_boundary_conditions_and_edge_case_robustness(): void
    {
        // 1. Extreme negative / nonexistent IDs
        $summaryNonexistent = $this->service->getSummary('2020-01-01', '2020-01-02', 999999, 999999, false);
        $this->assertEquals(0, $summaryNonexistent['orders_count']);
        $this->assertEquals(0.00, $summaryNonexistent['gross_sales']);
        $this->assertEquals(0.00, $summaryNonexistent['net_sales']);
        $this->assertEquals(0.00, $summaryNonexistent['average_order_value']);
        $this->assertEmpty($summaryNonexistent['top_selling_items']);
        $this->assertEmpty($summaryNonexistent['cashier_performance']);

        // 2. CSV Streaming with zero matching records
        $emptyHandle = tmpfile();
        $this->service->streamOrdersCsv($emptyHandle, '2020-01-01', '2020-01-02', 999999);
        rewind($emptyHandle);
        $content = stream_get_contents($emptyHandle);
        fclose($emptyHandle);

        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $lines = explode("\n", trim(substr($content, 3)));
        $this->assertCount(1, $lines, 'Only the header row should be present when 0 orders match.');

        // 3. Query count remains <= 4 even under extreme non-existent filters
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->service->getSummary('2020-01-01', '2020-01-02', 999999, 999999, false);

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(4, count($queries));
    }
}
