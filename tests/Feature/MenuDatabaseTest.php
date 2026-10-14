<?php

namespace Tests\Feature;

use App\Domains\Branches\Models\Branch;
use App\Domains\Identity\Models\User;
use App\Domains\Menu\Models\Meal;
use App\Domains\Menu\Models\MealCategory;
use Database\Seeders\MenuSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuDatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_menu_seeder_populates_categories_and_meals(): void
    {
        $this->seed(MenuSeeder::class);

        $this->assertGreaterThan(0, MealCategory::count());
        $this->assertGreaterThan(0, Meal::count());

        $risotto = Meal::where('menu_code', 'M-01')->first();
        $this->assertNotNull($risotto);
        $this->assertEquals('Truffle Mushroom Risotto', $risotto->name);
        $this->assertIsArray($risotto->kitchen_tags);
        $this->assertContains('vegetarian', $risotto->kitchen_tags);
    }

    public function test_meal_kitchen_tags_defaults_to_array_avoiding_null(): void
    {
        $category = MealCategory::create([
            'name' => 'Specials',
            'slug' => 'specials',
        ]);

        $meal = Meal::create([
            'category_id' => $category->id,
            'name' => 'Chef Omelette',
            'price' => 12.00,
        ]);

        $freshMeal = Meal::find($meal->id);
        $this->assertIsArray($freshMeal->kitchen_tags);
        $this->assertEmpty($freshMeal->kitchen_tags);
    }

    public function test_meal_soft_delete_preserves_historical_records(): void
    {
        $category = MealCategory::create([
            'name' => 'Starters',
            'slug' => 'starters',
        ]);

        $meal = Meal::create([
            'category_id' => $category->id,
            'name' => 'Soup of the Day',
            'price' => 7.50,
        ]);

        $meal->delete();

        $this->assertNull(Meal::find($meal->id));
        $this->assertNotNull(Meal::withTrashed()->find($meal->id));
        $this->assertTrue(Meal::withTrashed()->find($meal->id)->trashed());
    }
}
