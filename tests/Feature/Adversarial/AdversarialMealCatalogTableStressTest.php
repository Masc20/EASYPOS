<?php

namespace Tests\Feature\Adversarial;

use App\Domains\Branches\Models\Branch;
use App\Domains\Identity\Models\User;
use App\Domains\Menu\Livewire\MealCatalogTable;
use App\Domains\Menu\Models\Meal;
use App\Domains\Menu\Models\MealCategory;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AdversarialMealCatalogTableStressTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branch1;
    protected Branch $branch2;
    protected User $chef1;
    protected User $cashier1;
    protected MealCategory $categoryMain;
    protected MealCategory $categoryDrink;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->branch1 = Branch::where('code', 'B01')->firstOrFail();
        $this->branch2 = Branch::where('code', 'B02')->firstOrFail();

        $this->chef1 = User::factory()->create([
            'branch_id' => $this->branch1->id,
            'name' => 'Chef B1',
            'email' => 'chefb1@test.com',
        ]);
        $this->chef1->assignRole('chef');

        $this->cashier1 = User::factory()->create([
            'branch_id' => $this->branch1->id,
            'name' => 'Cashier B1',
            'email' => 'cashierb1@test.com',
        ]);
        $this->cashier1->assignRole('cashier');

        $this->categoryMain = MealCategory::create([
            'name' => 'Hot Mains',
            'slug' => 'hot-mains',
            'is_active' => true,
            'display_order' => 1,
        ]);

        $this->categoryDrink = MealCategory::create([
            'name' => 'Beverages',
            'slug' => 'beverages',
            'is_active' => true,
            'display_order' => 2,
        ]);
    }

    public function test_pagination_and_simple_paginate_bounds_rendering_without_full_count(): void
    {
        // Seed 50 meals for Branch 1
        for ($i = 1; $i <= 50; $i++) {
            Meal::create([
                'branch_id' => $this->branch1->id,
                'category_id' => $this->categoryMain->id,
                'name' => sprintf('Dish %02d', $i),
                'menu_code' => sprintf('DISH-%02d', $i),
                'price' => 100.00 + $i,
                'is_available' => true,
            ]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $component = Livewire::actingAs($this->chef1)
            ->test(MealCatalogTable::class);

        $mealsPage1 = $component->viewData('meals');
        $this->assertCount(24, $mealsPage1->items());
        $this->assertTrue($mealsPage1->hasMorePages());

        // Page 2
        $component->call('setPage', 2);
        $mealsPage2 = $component->viewData('meals');
        $this->assertCount(24, $mealsPage2->items());
        $this->assertTrue($mealsPage2->hasMorePages());

        // Page 3
        $component->call('setPage', 3);
        $mealsPage3 = $component->viewData('meals');
        $this->assertCount(2, $mealsPage3->items());
        $this->assertFalse($mealsPage3->hasMorePages());

        // Ensure no standalone SELECT count(*) queries on meals
        $queries = DB::getQueryLog();
        $standaloneCount = collect($queries)->filter(function ($q) {
            $sql = strtolower($q['query']);
            return str_contains($sql, 'select count(*) as aggregate from `meals`')
                || str_contains($sql, 'select count(*) as aggregate from "meals"');
        });
        $this->assertCount(0, $standaloneCount, 'Must avoid standalone count(*) on meals table.');
    }

    public function test_page_number_exceeding_available_pages_renders_cleanly(): void
    {
        Meal::create([
            'branch_id' => $this->branch1->id,
            'category_id' => $this->categoryMain->id,
            'name' => 'Single Dish',
            'menu_code' => 'SNG-01',
            'price' => 150.00,
            'is_available' => true,
        ]);

        $component = Livewire::actingAs($this->chef1)
            ->test(MealCatalogTable::class)
            ->call('setPage', 999);

        $meals = $component->viewData('meals');
        $this->assertCount(0, $meals->items());
        $this->assertFalse($meals->hasMorePages());
        $component->assertOk();
    }

    public function test_filter_and_search_changes_reset_pagination_to_page_one(): void
    {
        for ($i = 1; $i <= 30; $i++) {
            Meal::create([
                'branch_id' => $this->branch1->id,
                'category_id' => $this->categoryMain->id,
                'name' => sprintf('Dish %02d', $i),
                'menu_code' => sprintf('DISH-%02d', $i),
                'price' => 100.00,
                'is_available' => true,
            ]);
        }

        $component = Livewire::actingAs($this->chef1)
            ->test(MealCatalogTable::class)
            ->call('setPage', 2);

        $this->assertEquals(2, $component->instance()->getPage());

        // 1. Changing search resets page to 1
        $component->set('search', 'Dish 01');
        $this->assertEquals(1, $component->instance()->getPage());

        // 2. Changing category resets page to 1
        $component->call('setPage', 2);
        $this->assertEquals(2, $component->instance()->getPage());
        $component->call('filterByCategory', 'hot-mains');
        $this->assertEquals(1, $component->instance()->getPage());

        // 3. Changing status filter resets page to 1
        $component->call('setPage', 2);
        $this->assertEquals(2, $component->instance()->getPage());
        $component->call('filterByStatus', 'sold_out');
        $this->assertEquals(1, $component->instance()->getPage());
    }

    public function test_empty_category_and_non_existent_category_slug_render_cleanly(): void
    {
        $emptyCat = MealCategory::create([
            'name' => 'Empty Appetizers',
            'slug' => 'empty-appetizers',
            'is_active' => true,
            'display_order' => 3,
        ]);

        Meal::create([
            'branch_id' => $this->branch1->id,
            'category_id' => $this->categoryMain->id,
            'name' => 'Active Main',
            'price' => 200.00,
            'is_available' => true,
        ]);

        // 1. Select empty category
        $component = Livewire::actingAs($this->chef1)
            ->test(MealCatalogTable::class)
            ->call('filterByCategory', 'empty-appetizers');

        $meals = $component->viewData('meals');
        $this->assertCount(0, $meals->items());
        $component->assertOk();

        // 2. Select completely invalid slug
        $component->call('filterByCategory', 'invalid-slug-does-not-exist');
        $mealsInvalid = $component->viewData('meals');
        $this->assertCount(0, $mealsInvalid->items());
        $component->assertOk();
    }

    public function test_status_filtering_in_stock_vs_sold_out_and_live_toggle(): void
    {
        $dishInStock = Meal::create([
            'branch_id' => $this->branch1->id,
            'category_id' => $this->categoryMain->id,
            'name' => 'Available Adobo',
            'menu_code' => 'ADB-01',
            'price' => 220.00,
            'is_available' => true,
        ]);

        $dishSoldOut = Meal::create([
            'branch_id' => $this->branch1->id,
            'category_id' => $this->categoryMain->id,
            'name' => '86d Sinigang',
            'menu_code' => 'SNG-02',
            'price' => 260.00,
            'is_available' => false,
        ]);

        $component = Livewire::actingAs($this->chef1)
            ->test(MealCatalogTable::class);

        // 1. in_stock filter
        $component->call('filterByStatus', 'in_stock');
        $inStockMeals = $component->viewData('meals');
        $this->assertCount(1, $inStockMeals->items());
        $this->assertEquals($dishInStock->id, $inStockMeals->items()[0]->id);

        // 2. sold_out filter
        $component->call('filterByStatus', 'sold_out');
        $soldOutMeals = $component->viewData('meals');
        $this->assertCount(1, $soldOutMeals->items());
        $this->assertEquals($dishSoldOut->id, $soldOutMeals->items()[0]->id);

        // 3. all filter
        $component->call('filterByStatus', 'all');
        $allMeals = $component->viewData('meals');
        $this->assertCount(2, $allMeals->items());

        // 4. Toggle availability of available adobo -> now sold out
        $component->call('toggleAvailability', $dishInStock->id);
        $this->assertFalse($dishInStock->fresh()->is_available);

        // Under in_stock filter, it is now empty
        $component->call('filterByStatus', 'in_stock');
        $this->assertCount(0, $component->viewData('meals')->items());

        // Under sold_out filter, both are now present
        $component->call('filterByStatus', 'sold_out');
        $this->assertCount(2, $component->viewData('meals')->items());

        // 5. Invalid status filter falls back safely to returning all
        $component->call('filterByStatus', 'adversarial_random_status');
        $this->assertCount(2, $component->viewData('meals')->items());
    }

    public function test_adversarial_search_strings_behave_safely(): void
    {
        Meal::create([
            'branch_id' => $this->branch1->id,
            'category_id' => $this->categoryMain->id,
            'name' => 'Halo-Halo with 100% Leche Flan & Ice',
            'menu_code' => 'HH-100',
            'price' => 120.00,
            'is_available' => true,
        ]);

        $component = Livewire::actingAs($this->chef1)
            ->test(MealCatalogTable::class);

        $adversarialInputs = [
            '%',
            '_',
            "' OR '1'='1",
            "'; DROP TABLE meals; --",
            '<script>alert("xss")</script>',
            '🍔🍕🍣',
            '   ',
            'NonExistentCodeXYZ9999999',
            '\\',
            '#!--/*',
        ];

        foreach ($adversarialInputs as $input) {
            $component->set('search', $input);
            $component->assertOk();
            $meals = $component->viewData('meals');
            $this->assertNotNull($meals);
        }
    }

    public function test_branch_isolation_in_catalog_table(): void
    {
        $mealB1 = Meal::create([
            'branch_id' => $this->branch1->id,
            'category_id' => $this->categoryMain->id,
            'name' => 'Branch 1 Exclusive Paella',
            'price' => 450.00,
            'is_available' => true,
        ]);

        $mealB2 = Meal::create([
            'branch_id' => $this->branch2->id,
            'category_id' => $this->categoryMain->id,
            'name' => 'Branch 2 Exclusive Kare-Kare',
            'price' => 480.00,
            'is_available' => true,
        ]);

        $mealGlobal = Meal::create([
            'branch_id' => null,
            'category_id' => $this->categoryMain->id,
            'name' => 'HQ Universal Salad',
            'price' => 160.00,
            'is_available' => true,
        ]);

        $component = Livewire::actingAs($this->chef1)
            ->test(MealCatalogTable::class);

        $renderedMeals = $component->viewData('meals')->items();
        $mealIds = collect($renderedMeals)->pluck('id')->all();

        $this->assertContains($mealB1->id, $mealIds, 'Branch 1 chef must see Branch 1 meals');
        $this->assertContains($mealGlobal->id, $mealIds, 'Branch 1 chef must see Global meals');
        $this->assertNotContains($mealB2->id, $mealIds, 'Branch 1 chef must NEVER see Branch 2 meals');
    }

    public function test_unauthorized_user_cannot_toggle_or_delete_meals(): void
    {
        $meal = Meal::create([
            'branch_id' => $this->branch1->id,
            'category_id' => $this->categoryMain->id,
            'name' => 'Secure Dish',
            'price' => 180.00,
            'is_available' => true,
        ]);

        // Cashier has no menu.manage permission
        Livewire::actingAs($this->cashier1)
            ->test(MealCatalogTable::class)
            ->call('toggleAvailability', $meal->id)
            ->assertStatus(403);

        Livewire::actingAs($this->cashier1)
            ->test(MealCatalogTable::class)
            ->call('deleteMeal', $meal->id)
            ->assertStatus(403);
    }
}
