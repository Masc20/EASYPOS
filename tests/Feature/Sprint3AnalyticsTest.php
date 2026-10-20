<?php

namespace Tests\Feature;

use App\Domains\Identity\Models\User;
use App\Domains\Menu\Models\Meal;
use App\Domains\Menu\Models\MealCategory;
use App\Domains\PointOfSale\Models\Order;
use App\Domains\PointOfSale\Models\OrderItem;
use App\Domains\PointOfSale\Models\Payment;
use App\Domains\PointOfSale\Services\SalesAggregationService;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\SalesRecords;
use App\Filament\Widgets\PeakSalesHoursChart;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class Sprint3AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $owner;
    protected User $cashier;
    protected MealCategory $categoryBurgers;
    protected MealCategory $categoryDrinks;
    protected Meal $mealBurgerA;
    protected Meal $mealBurgerB;
    protected Meal $mealSoda;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->admin = User::where('email', 'admin@easypos.com')->firstOrFail();
        $this->owner = User::where('email', 'owner@easypos.com')->firstOrFail();
        $this->cashier = User::where('emp_id', 'B01-CSH-001')->firstOrFail();

        // Create categories and meals for deterministic best-seller & category tests
        $this->categoryBurgers = MealCategory::create([
            'name' => 'Gourmet Burgers',
            'slug' => 'gourmet-burgers',
            'is_active' => true,
        ]);

        $this->categoryDrinks = MealCategory::create([
            'name' => 'Beverages',
            'slug' => 'beverages',
            'is_active' => true,
        ]);

        $this->mealBurgerA = Meal::create([
            'name' => 'Classic Cheeseburger',
            'menu_code' => 'B001',
            'category_id' => $this->categoryBurgers->id,
            'price' => 200.00,
            'is_available' => true,
            'is_active' => true,
        ]);

        $this->mealBurgerB = Meal::create([
            'name' => 'Bacon Deluxe Burger',
            'menu_code' => 'B002',
            'category_id' => $this->categoryBurgers->id,
            'price' => 250.00,
            'is_available' => true,
            'is_active' => true,
        ]);

        $this->mealSoda = Meal::create([
            'name' => 'Iced Cola 500ml',
            'menu_code' => 'D001',
            'category_id' => $this->categoryDrinks->id,
            'price' => 50.00,
            'is_available' => true,
            'is_active' => true,
        ]);
    }

    /**
     * SCRUM-170: Audit best-seller ranking counts, tie-breaking criteria,
     * category filtering, and date cutoff boundaries.
     */
    public function test_best_selling_rankings_math_tie_breaking_and_cutoffs(): void
    {
        $service = app(SalesAggregationService::class);

        // Order 1 (Today at 12:00 PM): 5 Classic Cheeseburgers (5 * 200 = 1000)
        $order1 = Order::create([
            'order_number' => 'ORD-RANK-001',
            'branch_id' => 1,
            'cashier_id' => $this->cashier->id,
            'order_type' => 'dine_in',
            'subtotal' => 1000.00,
            'discount_amount' => 0.00,
            'tax_amount' => 0.00,
            'total_amount' => 1000.00,
            'status' => 'completed',
            'payment_status' => 'paid',
            'created_at' => Carbon::now()->setTime(12, 0),
        ]);
        OrderItem::create([
            'order_id' => $order1->id,
            'meal_id' => $this->mealBurgerA->id,
            'meal_name' => $this->mealBurgerA->name,
            'unit_price' => 200.00,
            'quantity' => 5,
            'subtotal' => 1000.00,
        ]);

        // Order 2 (Today at 12:30 PM): 5 Bacon Deluxe Burgers (5 * 250 = 1250) -> Tied on quantity with Burger A (5 vs 5), but higher revenue (1250 vs 1000)!
        $order2 = Order::create([
            'order_number' => 'ORD-RANK-002',
            'branch_id' => 1,
            'cashier_id' => $this->cashier->id,
            'order_type' => 'dine_in',
            'subtotal' => 1250.00,
            'discount_amount' => 0.00,
            'tax_amount' => 0.00,
            'total_amount' => 1250.00,
            'status' => 'completed',
            'payment_status' => 'paid',
            'created_at' => Carbon::now()->setTime(12, 30),
        ]);
        OrderItem::create([
            'order_id' => $order2->id,
            'meal_id' => $this->mealBurgerB->id,
            'meal_name' => $this->mealBurgerB->name,
            'unit_price' => 250.00,
            'quantity' => 5,
            'subtotal' => 1250.00,
        ]);

        // Order 3 (Today at 1:00 PM): 10 Iced Colas (10 * 50 = 500) -> Higher volume (10), lower revenue (500)
        $order3 = Order::create([
            'order_number' => 'ORD-RANK-003',
            'branch_id' => 1,
            'cashier_id' => $this->cashier->id,
            'order_type' => 'takeout',
            'subtotal' => 500.00,
            'discount_amount' => 0.00,
            'tax_amount' => 0.00,
            'total_amount' => 500.00,
            'status' => 'completed',
            'payment_status' => 'paid',
            'created_at' => Carbon::now()->setTime(13, 0),
        ]);
        OrderItem::create([
            'order_id' => $order3->id,
            'meal_id' => $this->mealSoda->id,
            'meal_name' => $this->mealSoda->name,
            'unit_price' => 50.00,
            'quantity' => 10,
            'subtotal' => 500.00,
        ]);

        // Order 4 (Past month - should be excluded when filtering for today): 20 Colas
        $orderOld = Order::create([
            'order_number' => 'ORD-RANK-OLD',
            'branch_id' => 1,
            'cashier_id' => $this->cashier->id,
            'order_type' => 'takeout',
            'subtotal' => 1000.00,
            'discount_amount' => 0.00,
            'tax_amount' => 0.00,
            'total_amount' => 1000.00,
            'status' => 'completed',
            'payment_status' => 'paid',
            'created_at' => Carbon::now()->subMonths(2),
        ]);
        OrderItem::create([
            'order_id' => $orderOld->id,
            'meal_id' => $this->mealSoda->id,
            'meal_name' => $this->mealSoda->name,
            'unit_price' => 50.00,
            'quantity' => 20,
            'subtotal' => 1000.00,
        ]);

        $todayStr = Carbon::today()->toDateString();

        // 1. Test Sort By Volume (Quantity desc):
        // Expected #1: Iced Cola (10 qty)
        // Expected #2: Bacon Deluxe Burger (5 qty, ₱1250) - wins tie against Cheeseburger (5 qty, ₱1000)
        // Expected #3: Classic Cheeseburger (5 qty, ₱1000)
        $rankingsByQty = $service->getBestSellingRankings(
            startDate: $todayStr,
            endDate: $todayStr,
            sortBy: 'quantity',
            useCache: false
        );

        $this->assertCount(3, $rankingsByQty);
        $this->assertEquals('Iced Cola 500ml', $rankingsByQty[0]['name']);
        $this->assertEquals(10, $rankingsByQty[0]['quantity']);
        $this->assertEquals(1, $rankingsByQty[0]['rank']);

        // Tie-breaker verification: Bacon Deluxe has same quantity as Cheeseburger, but higher revenue
        $this->assertEquals('Bacon Deluxe Burger', $rankingsByQty[1]['name']);
        $this->assertEquals(5, $rankingsByQty[1]['quantity']);
        $this->assertEquals(1250.00, $rankingsByQty[1]['revenue']);
        $this->assertEquals(2, $rankingsByQty[1]['rank']);

        $this->assertEquals('Classic Cheeseburger', $rankingsByQty[2]['name']);
        $this->assertEquals(5, $rankingsByQty[2]['quantity']);
        $this->assertEquals(1000.00, $rankingsByQty[2]['revenue']);
        $this->assertEquals(3, $rankingsByQty[2]['rank']);

        // 2. Test Sort By Revenue:
        // Expected #1: Bacon Deluxe Burger (₱1250)
        // Expected #2: Classic Cheeseburger (₱1000)
        // Expected #3: Iced Cola 500ml (₱500)
        $rankingsByRev = $service->getBestSellingRankings(
            startDate: $todayStr,
            endDate: $todayStr,
            sortBy: 'revenue',
            useCache: false
        );

        $this->assertEquals('Bacon Deluxe Burger', $rankingsByRev[0]['name']);
        $this->assertEquals(1250.00, $rankingsByRev[0]['revenue']);
        $this->assertEquals('Classic Cheeseburger', $rankingsByRev[1]['name']);
        $this->assertEquals(1000.00, $rankingsByRev[1]['revenue']);
        $this->assertEquals('Iced Cola 500ml', $rankingsByRev[2]['name']);
        $this->assertEquals(500.00, $rankingsByRev[2]['revenue']);

        // 3. Test Category Filtering (Only Beverages):
        $beverageRankings = $service->getBestSellingRankings(
            startDate: $todayStr,
            endDate: $todayStr,
            categoryId: $this->categoryDrinks->id,
            useCache: false
        );

        $this->assertCount(1, $beverageRankings);
        $this->assertEquals('Iced Cola 500ml', $beverageRankings[0]['name']);
        $this->assertEquals('Beverages', $beverageRankings[0]['category_name']);

        // 4. Test Category Aggregate Performance Breakdown:
        $categoryBreakdown = $service->getCategoryRankings(
            startDate: $todayStr,
            endDate: $todayStr
        );

        $this->assertCount(2, $categoryBreakdown);
        $burgerCat = collect($categoryBreakdown)->firstWhere('category_name', 'Gourmet Burgers');
        $this->assertNotNull($burgerCat);
        $this->assertEquals(2250.00, $burgerCat['revenue']);
        $this->assertEquals(10, $burgerCat['quantity']);

        $drinksCat = collect($categoryBreakdown)->firstWhere('category_name', 'Beverages');
        $this->assertNotNull($drinksCat);
        $this->assertEquals(500.00, $drinksCat['revenue']);
        $this->assertEquals(10, $drinksCat['quantity']);
    }

    /**
     * SCRUM-174: Reconcile periodic report totals, test intervals (day, week, month, year),
     * BIR tax reconciliation, and export integrity.
     */
    public function test_periodic_sales_report_reconciliation_and_exports(): void
    {
        $service = app(SalesAggregationService::class);

        // Day 1 (Vatable sales: ₱1120 gross, ₱0 discount -> ₱1000 net vatable + ₱120 VAT)
        $order1 = Order::create([
            'order_number' => 'ORD-PERIODIC-001',
            'branch_id' => 1,
            'cashier_id' => $this->cashier->id,
            'order_type' => 'dine_in',
            'subtotal' => 1120.00,
            'discount_amount' => 0.00,
            'tax_amount' => 120.00,
            'total_amount' => 1120.00,
            'status' => 'completed',
            'payment_status' => 'paid',
            'created_at' => Carbon::parse('2026-10-15 11:30:00'),
        ]);

        // Day 2 (VAT-Exempt Senior Citizen sales: ₱500 gross, ₱100 discount -> ₱400 net VAT exempt)
        $order2 = Order::create([
            'order_number' => 'ORD-PERIODIC-002',
            'branch_id' => 1,
            'cashier_id' => $this->cashier->id,
            'order_type' => 'takeout',
            'subtotal' => 500.00,
            'discount_amount' => 100.00,
            'tax_amount' => 0.00,
            'total_amount' => 400.00,
            'status' => 'completed',
            'payment_status' => 'paid',
            'created_at' => Carbon::parse('2026-10-16 14:00:00'),
        ]);

        // 1. Daily Report
        $dailyReport = $service->getPeriodicReport(
            interval: 'day',
            startDate: '2026-10-15',
            endDate: '2026-10-16'
        );

        $this->assertEquals('day', $dailyReport['interval']);
        $this->assertCount(2, $dailyReport['items']);
        $this->assertEquals(2, $dailyReport['totals']['orders_count']);
        $this->assertEquals(1620.00, $dailyReport['totals']['gross_sales']);
        $this->assertEquals(100.00, $dailyReport['totals']['total_discounts']);
        $this->assertEquals(1520.00, $dailyReport['totals']['net_sales']);
        $this->assertEquals(1000.00, $dailyReport['totals']['vatable_sales']);
        $this->assertEquals(120.00, $dailyReport['totals']['vat_amount']);
        $this->assertEquals(400.00, $dailyReport['totals']['vat_exempt_sales']);
        $this->assertEquals(760.00, $dailyReport['totals']['average_order_value']);

        // 2. Monthly Report (Both orders in 2026-10)
        $monthlyReport = $service->getPeriodicReport(
            interval: 'month',
            startDate: '2026-10-01',
            endDate: '2026-10-31'
        );

        $this->assertCount(1, $monthlyReport['items']);
        $this->assertEquals(1520.00, $monthlyReport['items'][0]['net_sales']);
        $this->assertEquals('October 2026', $monthlyReport['items'][0]['period_label']);

        // 3. Yearly Report
        $yearlyReport = $service->getPeriodicReport(
            interval: 'year',
            startDate: '2026-01-01',
            endDate: '2026-12-31'
        );

        $this->assertCount(1, $yearlyReport['items']);
        $this->assertEquals('Year 2026', $yearlyReport['items'][0]['period_label']);

        // 4. Test Periodic CSV Export format & headers
        $csv = $service->exportPeriodicCsv(
            interval: 'day',
            startDate: '2026-10-15',
            endDate: '2026-10-16'
        );

        $this->assertStringContainsString('Period (Day)', $csv);
        $this->assertStringContainsString('Gross Sales (PHP)', $csv);
        $this->assertStringContainsString('Net Sales (PHP)', $csv);
        $this->assertStringContainsString('VATable Sales (PHP)', $csv);
        $this->assertStringContainsString('VAT Amount 12% (PHP)', $csv);
        $this->assertStringContainsString('VAT-Exempt Sales (PHP)', $csv);
        $this->assertStringContainsString('TOTAL / SUMMARY', $csv);
        $this->assertStringContainsString('1520.00', $csv);
    }

    /**
     * SCRUM-178: Dashboard metric precision, peak-hour rush categorization,
     * lunch rush vs dinner rush analytics.
     */
    public function test_hourly_sales_distribution_and_peak_hour_analytics(): void
    {
        $service = app(SalesAggregationService::class);
        $targetDate = '2026-10-20';

        // 3 Orders at Lunch Rush (12:00 PM, hour 12): Total ₱1500
        for ($i = 1; $i <= 3; $i++) {
            Order::create([
                'order_number' => "ORD-LUNCH-{$i}",
                'branch_id' => 1,
                'cashier_id' => $this->cashier->id,
                'order_type' => 'dine_in',
                'subtotal' => 500.00,
                'discount_amount' => 0.00,
                'tax_amount' => 0.00,
                'total_amount' => 500.00,
                'status' => 'completed',
                'payment_status' => 'paid',
                'created_at' => Carbon::parse("{$targetDate} 12:15:00"),
            ]);
        }

        // 1 Order at Dinner Rush (6:00 PM, hour 18): Total ₱800
        Order::create([
            'order_number' => 'ORD-DINNER-1',
            'branch_id' => 1,
            'cashier_id' => $this->cashier->id,
            'order_type' => 'takeout',
            'subtotal' => 800.00,
            'discount_amount' => 0.00,
            'tax_amount' => 0.00,
            'total_amount' => 800.00,
            'status' => 'completed',
            'payment_status' => 'paid',
            'created_at' => Carbon::parse("{$targetDate} 18:30:00"),
        ]);

        $hourly = $service->getHourlySalesDistribution(
            startDate: $targetDate,
            endDate: $targetDate
        );

        $this->assertCount(24, $hourly['hours']);
        $this->assertEquals(4, $hourly['total_orders']);
        $this->assertEquals(2300.00, $hourly['total_revenue']);

        // Peak hour must be 12 (12 PM)
        $this->assertEquals(12, $hourly['peak_hour']);
        $this->assertEquals('12:00 PM - 1:00 PM', $hourly['peak_hour_label']);
        $this->assertEquals(3, $hourly['peak_orders_count']);
        $this->assertEquals(1500.00, $hourly['peak_revenue']);

        // Lunch rush (11am-2pm) should contain 3 orders, ₱1500
        $this->assertEquals(3, $hourly['lunch_rush']['orders_count']);
        $this->assertEquals(1500.00, $hourly['lunch_rush']['revenue']);

        // Dinner rush (5pm-9pm) should contain 1 order, ₱800
        $this->assertEquals(1, $hourly['dinner_rush']['orders_count']);
        $this->assertEquals(800.00, $hourly['dinner_rush']['revenue']);
    }

    /**
     * UI Integration: Livewire SalesRecords interactions with Sprint 3 widgets and print audit route.
     */
    public function test_livewire_sales_records_sprint3_tabs_and_audit_route(): void
    {
        // 1. Test Smart Executive Dashboard (Peak rush, best sellers, KPIs)
        Livewire::actingAs($this->owner)
            ->test(Dashboard::class)
            ->assertSuccessful()
            ->assertSee('Peak Sales Rush')
            ->assertSee('Lunch Rush (11 AM - 2 PM)')
            ->assertSee('Dinner Rush (5 PM - 9 PM)')
            ->assertSee('Top 5 Best-Selling Menu Items')
            // Test sorting best sellers
            ->call('setBestSellerSort', 'revenue')
            ->assertSet('bestSellerSortBy', 'revenue')
            // Test changing limit
            ->call('setBestSellerLimit', 20)
            ->assertSet('bestSellerLimit', 20);

        // 2. Test Sales Records & Financial Ledger (Periodic reports, cash drawer, BIR compliance)
        Livewire::actingAs($this->owner)
            ->test(SalesRecords::class)
            ->assertSuccessful()
            ->assertSee('Periodic Sales Reports (Daily / Weekly / Monthly / Yearly)')
            ->assertSee('Export Periodic CSV')
            ->assertSee('Print Audit Report')
            // Test switching intervals
            ->call('setPeriodicInterval', 'monthly')
            ->assertSet('periodicInterval', 'month');

        // Test PeakSalesHoursChart widget renders successfully
        Livewire::actingAs($this->owner)
            ->test(PeakSalesHoursChart::class)
            ->assertSuccessful();

        // Test Printable Audit Report route returns 200 with layout
        $response = $this->actingAs($this->owner)->get(route('sales-records.audit-report', [
            'interval' => 'day',
            'startDate' => Carbon::today()->toDateString(),
            'endDate' => Carbon::today()->toDateString(),
        ]));

        $response->assertStatus(200);
        $response->assertSee('Periodic Sales Audit');
        $response->assertSee('BIR Tax Compliance Documentation');
        $response->assertSee('Print Audit Report / Save PDF');
    }
}
