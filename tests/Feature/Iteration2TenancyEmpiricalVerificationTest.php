<?php

namespace Tests\Feature;

use App\Domains\Branches\Models\Branch;
use App\Domains\Identity\Models\User;
use App\Domains\Menu\Models\Meal;
use App\Domains\Menu\Models\MealCategory;
use App\Domains\PointOfSale\Models\Order;
use App\Domains\PointOfSale\Models\OrderItem;
use App\Domains\PointOfSale\Services\SalesAggregationService;
use App\Support\Enums\OrderStatus;
use App\Support\Enums\PaymentStatus;
use App\Support\Enums\StaffRole;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class Iteration2TenancyEmpiricalVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_multi_branch_tenancy_and_cache_key_isolation(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        Cache::flush();

        $branchA = Branch::firstOrCreate(['code' => 'BR-A'], ['name' => 'Branch Alpha', 'is_active' => true]);
        $branchB = Branch::firstOrCreate(['code' => 'BR-B'], ['name' => 'Branch Beta', 'is_active' => true]);

        $managerA = User::create([
            'name' => 'Manager A',
            'email' => 'manager_a_tenancy@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'MGR-A-TEN',
            'branch_id' => $branchA->id,
            'is_active' => true,
        ]);
        $managerA->assignRole(StaffRole::BRANCH_MANAGER->value);

        $catA = MealCategory::create(['name' => 'Alpha Meals', 'slug' => 'alpha-meals', 'is_active' => true]);
        $catB = MealCategory::create(['name' => 'Beta Top Secret', 'slug' => 'beta-top-secret', 'is_active' => true]);

        $mealA = Meal::create(['name' => 'Alpha Dish', 'menu_code' => 'AL-01', 'category_id' => $catA->id, 'price' => 100, 'is_available' => true, 'is_active' => true]);
        $mealB = Meal::create(['name' => 'Beta Secret Dish', 'menu_code' => 'BE-01', 'category_id' => $catB->id, 'price' => 500, 'is_available' => true, 'is_active' => true]);

        $orderA = Order::create([
            'order_number' => 'ORD-A-1',
            'branch_id' => $branchA->id,
            'cashier_id' => $managerA->id,
            'subtotal' => 100.00,
            'total_amount' => 100.00,
            'status' => OrderStatus::COMPLETED->value,
            'payment_status' => PaymentStatus::PAID->value,
            'created_at' => now(),
        ]);
        OrderItem::create([
            'order_id' => $orderA->id,
            'meal_id' => $mealA->id,
            'meal_name' => $mealA->name,
            'unit_price' => 100.00,
            'quantity' => 1,
            'subtotal' => 100.00,
        ]);

        $orderB = Order::create([
            'order_number' => 'ORD-B-1',
            'branch_id' => $branchB->id,
            'cashier_id' => $managerA->id,
            'subtotal' => 500.00,
            'total_amount' => 500.00,
            'status' => OrderStatus::COMPLETED->value,
            'payment_status' => PaymentStatus::PAID->value,
            'created_at' => now(),
        ]);
        OrderItem::create([
            'order_id' => $orderB->id,
            'meal_id' => $mealB->id,
            'meal_name' => $mealB->name,
            'unit_price' => 500.00,
            'quantity' => 1,
            'subtotal' => 500.00,
        ]);

        $service = app(SalesAggregationService::class);
        $this->actingAs($managerA);

        // 1. Verify getCategoryRankings(null, null, null)
        $rankings = $service->getCategoryRankings(null, null, null);
        $categoryNames = array_column($rankings, 'category_name');

        $this->assertContains('Alpha Meals', $categoryNames);
        $this->assertNotContains('Beta Top Secret', $categoryNames);

        // Verify cache key generated
        $expectedCacheKey = "sales_cat_rankings_b{$branchA->id}_call_none_none";
        $this->assertTrue(Cache::has($expectedCacheKey), "Cache must contain key: {$expectedCacheKey}");
        $this->assertFalse(Cache::has("sales_cat_rankings_bglobal_call_none_none"), "Cache must NOT contain global key");
        $this->assertFalse(Cache::has("sales_cat_rankings_b{$branchB->id}_call_none_none"), "Cache must NOT contain branch B key");

        // 2. Verify getPeriodicReport('day', null, null, null)
        $report = $service->getPeriodicReport('day', null, null, null);
        $this->assertEquals(100.00, $report['totals']['net_sales'], 'Branch A Manager calling getPeriodicReport with null branch must see only Branch A total net sales ($100).');
        $this->assertEquals(1, $report['totals']['orders_count'], 'Branch A Manager calling getPeriodicReport with null branch must see only 1 order.');

        // 3. Verify getPeriodicReport('day', null, null, $branchB->id) tampering attempt
        $tamperedReport = $service->getPeriodicReport('day', null, null, $branchB->id);
        $this->assertEquals(100.00, $tamperedReport['totals']['net_sales'], 'Branch A Manager attempting to query Branch B must be forced to Branch A totals ($100).');

        // 4. Verify getSummary(null, null, null)
        $summary = $service->getSummary(null, null, null);
        $this->assertEquals(100.00, $summary['net_sales'], 'Branch A Manager summary must only include Branch A net sales.');
        $expectedSummaryKey = "sales_summary_b{$branchA->id}_call_none_none";
        $this->assertTrue(Cache::has($expectedSummaryKey), "Cache must contain key: {$expectedSummaryKey}");
        $this->assertFalse(Cache::has("sales_summary_bglobal_call_none_none"));

        // 5. Verify getBestSellingRankings(null, null, null)
        $bestSellers = $service->getBestSellingRankings(null, null, null);
        $bestSellerNames = array_column($bestSellers, 'name');
        $this->assertContains('Alpha Dish', $bestSellerNames);
        $this->assertNotContains('Beta Secret Dish', $bestSellerNames);
        $expectedBestSellersKey = "sales_best_sellers_b{$branchA->id}_call_all_10_quantity_none_none";
        $this->assertTrue(Cache::has($expectedBestSellersKey), "Cache must contain key: {$expectedBestSellersKey}");
        $this->assertFalse(Cache::has("sales_best_sellers_bglobal_call_all_10_quantity_none_none"));

        // 6. Verify getHourlySalesDistribution(null, null, null)
        $hourly = $service->getHourlySalesDistribution(null, null, null);
        $this->assertEquals(100.00, $hourly['total_revenue']);
        $this->assertEquals(1, $hourly['total_orders']);
        $expectedHourlyKey = "sales_hourly_b{$branchA->id}_call_none_none";
        $this->assertTrue(Cache::has($expectedHourlyKey), "Cache must contain key: {$expectedHourlyKey}");
        $this->assertFalse(Cache::has("sales_hourly_bglobal_call_none_none"));
    }

    public function test_owner_global_unscoped_visibility(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        Cache::flush();

        $branchA = Branch::firstOrCreate(['code' => 'BR-A'], ['name' => 'Branch Alpha', 'is_active' => true]);
        $branchB = Branch::firstOrCreate(['code' => 'BR-B'], ['name' => 'Branch Beta', 'is_active' => true]);

        $owner = User::firstOrCreate(['email' => 'owner_tenancy@easypos.test'], [
            'name' => 'Owner',
            'password' => Hash::make('password'),
            'emp_id' => 'HQ-OWN-TEN',
            'is_active' => true,
        ]);
        if (! $owner->hasRole(StaffRole::OWNER->value)) {
            $owner->assignRole(StaffRole::OWNER->value);
        }

        $catA = MealCategory::create(['name' => 'Alpha Meals 2', 'slug' => 'alpha-meals-2', 'is_active' => true]);
        $catB = MealCategory::create(['name' => 'Beta Secret 2', 'slug' => 'beta-secret-2', 'is_active' => true]);

        $mealA = Meal::create(['name' => 'Alpha Dish 2', 'menu_code' => 'AL-02', 'category_id' => $catA->id, 'price' => 100, 'is_available' => true, 'is_active' => true]);
        $mealB = Meal::create(['name' => 'Beta Secret Dish 2', 'menu_code' => 'BE-02', 'category_id' => $catB->id, 'price' => 500, 'is_available' => true, 'is_active' => true]);

        Order::create([
            'order_number' => 'ORD-A-2',
            'branch_id' => $branchA->id,
            'cashier_id' => $owner->id,
            'subtotal' => 100.00,
            'total_amount' => 100.00,
            'status' => OrderStatus::COMPLETED->value,
            'payment_status' => PaymentStatus::PAID->value,
            'created_at' => now(),
        ]);

        Order::create([
            'order_number' => 'ORD-B-2',
            'branch_id' => $branchB->id,
            'cashier_id' => $owner->id,
            'subtotal' => 500.00,
            'total_amount' => 500.00,
            'status' => OrderStatus::COMPLETED->value,
            'payment_status' => PaymentStatus::PAID->value,
            'created_at' => now(),
        ]);

        $service = app(SalesAggregationService::class);
        $this->actingAs($owner);

        // Owner passing null gets global aggregate across all branches
        $report = $service->getPeriodicReport('day', null, null, null);
        $this->assertEquals(600.00, $report['totals']['net_sales'], 'Owner calling getPeriodicReport with null branch sees all branches aggregate ($600).');
        $this->assertEquals(2, $report['totals']['orders_count']);

        // Owner passing specific branch gets that branch
        $reportB = $service->getPeriodicReport('day', null, null, $branchB->id);
        $this->assertEquals(500.00, $reportB['totals']['net_sales'], 'Owner filtering by Branch B sees Branch B only ($500).');
    }
}
