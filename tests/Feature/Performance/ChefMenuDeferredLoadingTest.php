<?php

namespace Tests\Feature\Performance;

use App\Domains\Identity\Models\User;
use App\Domains\Menu\Livewire\CreateMealModal;
use App\Domains\Menu\Livewire\DailySpecialsBoard;
use App\Domains\Menu\Livewire\EditMealModal;
use App\Domains\Menu\Livewire\MealCatalogTable;
use App\Domains\Menu\Models\Meal;
use App\Domains\Menu\Models\MealCategory;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class ChefMenuDeferredLoadingTest extends TestCase
{
    use RefreshDatabase;

    protected User $chefB1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(MenuSeeder::class);

        $this->chefB1 = User::where('emp_id', 'B01-CHF-001')->firstOrFail();
    }

    public function test_menu_page_does_not_execute_category_queries_for_closed_modals(): void
    {
        // 1. Clear any cached categories
        Cache::forget('meal_categories_active');

        // 2. Test CreateMealModal closed state
        DB::flushQueryLog();
        DB::enableQueryLog();

        $createModal = Livewire::actingAs($this->chefB1)
            ->test(CreateMealModal::class);

        $createModal->assertSet('isOpen', false);
        $this->assertCount(0, $createModal->viewData('categories'), 'CreateMealModal categories must be empty when closed.');

        $createQueries = DB::getQueryLog();
        $createCategoryQueries = collect($createQueries)->filter(fn ($q) => str_contains($q['query'], 'meal_categories'));
        $this->assertCount(0, $createCategoryQueries, 'Closed CreateMealModal must not query meal_categories.');

        // 3. Test EditMealModal closed state
        DB::flushQueryLog();
        DB::enableQueryLog();

        $editModal = Livewire::actingAs($this->chefB1)
            ->test(EditMealModal::class);

        $editModal->assertSet('isOpen', false);
        $this->assertCount(0, $editModal->viewData('categories'), 'EditMealModal categories must be empty when closed.');

        $editQueries = DB::getQueryLog();
        $editCategoryQueries = collect($editQueries)->filter(fn ($q) => str_contains($q['query'], 'meal_categories'));
        $this->assertCount(0, $editCategoryQueries, 'Closed EditMealModal must not query meal_categories.');

        // 4. Verify HTTP GET /menu responds successfully
        $response = $this->actingAs($this->chefB1)->get(route('menu'));
        $response->assertOk();
    }

    public function test_create_meal_modal_hydrates_categories_and_auto_generates_code_on_open(): void
    {
        $component = Livewire::actingAs($this->chefB1)
            ->test(CreateMealModal::class)
            ->assertSet('isOpen', false);

        // Open modal via dedicated event
        $component->dispatch('open-create-meal-modal')
            ->assertSet('isOpen', true);

        // Assert categories are hydrated from active categories
        $categories = $component->viewData('categories');
        $this->assertTrue($categories->isNotEmpty(), 'Categories must be loaded upon opening CreateMealModal.');

        // Assert default category is assigned and menu code is auto-generated
        $this->assertNotNull($component->get('category_id'), 'Default category_id must be assigned.');
        $this->assertNotEmpty($component->get('menu_code'), 'Menu code must be auto-generated.');

        // Set dish fields and save
        $component->set('name', 'Pork Sisig Kapampangan')
            ->set('price', '280.00')
            ->set('description', 'Authentic sizzling pork sisig')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('close-modal', 'create-meal-modal')
            ->assertDispatched('meal-saved');

        // Verify meal record in database
        $meal = Meal::where('name', 'Pork Sisig Kapampangan')->first();
        $this->assertNotNull($meal);
        $this->assertEquals($this->chefB1->branch_id, $meal->branch_id);
        $this->assertEquals('280.00', $meal->price);
        $this->assertNotNull($meal->category_id);
    }

    public function test_category_master_cache_invalidation_on_category_changes(): void
    {
        // 1. Warm the active categories cache
        $cached = Cache::remember('meal_categories_active', 3600, function () {
            return MealCategory::where('is_active', true)->orderBy('display_order')->get();
        });
        $this->assertTrue(Cache::has('meal_categories_active'));
        $this->assertNotEmpty($cached);

        // 2. Create new category -> verify cache invalidated
        $category = MealCategory::create([
            'name' => 'Signature Platters',
            'slug' => 'signature-platters',
            'is_active' => true,
            'display_order' => 99,
        ]);
        $this->assertFalse(Cache::has('meal_categories_active'), 'Cache must be invalidated when a new category is created.');

        // 3. Warm the cache again
        Cache::remember('meal_categories_active', 3600, function () {
            return MealCategory::where('is_active', true)->orderBy('display_order')->get();
        });
        $this->assertTrue(Cache::has('meal_categories_active'));

        // 4. Update category -> verify cache invalidated
        $category->update(['name' => 'Signature Chef Platters']);
        $this->assertFalse(Cache::has('meal_categories_active'), 'Cache must be invalidated when a category is updated.');

        // 5. Warm the cache again
        Cache::remember('meal_categories_active', 3600, function () {
            return MealCategory::where('is_active', true)->orderBy('display_order')->get();
        });
        $this->assertTrue(Cache::has('meal_categories_active'));

        // 6. Delete category -> verify cache invalidated
        $category->delete();
        $this->assertFalse(Cache::has('meal_categories_active'), 'Cache must be invalidated when a category is deleted.');
    }

    public function test_daily_specials_board_bounds_queries(): void
    {
        $branchId = $this->chefB1->branch_id;
        $category = MealCategory::firstOrFail();

        // Seed 25 meals with track_stock = true for this branch
        for ($i = 1; $i <= 25; $i++) {
            Meal::create([
                'branch_id' => $branchId,
                'category_id' => $category->id,
                'name' => "Special Dish {$i}",
                'menu_code' => "SPC-{$i}-" . uniqid(),
                'price' => 120.00,
                'is_available' => true,
                'track_stock' => true,
                'stock_quantity' => $i,
            ]);
        }

        $component = Livewire::actingAs($this->chefB1)
            ->test(DailySpecialsBoard::class);

        $specials = $component->viewData('limitedSpecials');
        $this->assertLessThanOrEqual(15, $specials->count(), 'Daily specials board must bound limitedSpecials to at most 15 items.');
    }

    public function test_meal_catalog_table_avoids_redundant_count_query(): void
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $component = Livewire::actingAs($this->chefB1)
            ->test(MealCatalogTable::class);

        $totalDishesCount = $component->viewData('totalDishesCount');
        $this->assertGreaterThan(0, $totalDishesCount);

        $queries = DB::getQueryLog();
        // Check if there was any standalone 'select count(*) as aggregate from `meals`' without category joins
        $standaloneCountQueries = collect($queries)->filter(function ($q) {
            $query = strtolower($q['query']);
            return str_contains($query, 'select count(*) as aggregate from `meals`') ||
                   str_contains($query, 'select count(*) as aggregate from "meals"');
        });

        $this->assertCount(0, $standaloneCountQueries, 'MealCatalogTable must avoid running standalone count(*) queries on meals.');
    }
}
