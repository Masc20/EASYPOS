<?php

namespace Tests\Feature;

use App\Domains\Branches\Models\Branch;
use App\Domains\Identity\Models\User;
use App\Domains\Menu\Livewire\DailySpecialsBoard;
use App\Domains\Menu\Livewire\MealCatalogTable;
use App\Domains\Menu\Models\Meal;
use App\Domains\Menu\Models\MealCategory;
use Database\Seeders\MenuSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MenuDatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_menu_seeder_populates_categories_and_meals_with_peso_prices(): void
    {
        $this->seed(MenuSeeder::class);

        $this->assertGreaterThan(0, MealCategory::count());
        $this->assertGreaterThan(0, Meal::count());

        $risotto = Meal::where('menu_code', 'M-01')->first();
        $this->assertNotNull($risotto);
        $this->assertEquals('Truffle Mushroom Risotto', $risotto->name);
        $this->assertEquals('420.00', $risotto->price);
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
            'price' => 250.00,
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
            'price' => 180.00,
        ]);

        $meal->delete();

        $this->assertNull(Meal::find($meal->id));
        $this->assertNotNull(Meal::withTrashed()->find($meal->id));
        $this->assertTrue(Meal::withTrashed()->find($meal->id)->trashed());
    }

    public function test_meal_catalog_table_renders_with_peso_symbols(): void
    {
        $this->seed(MenuSeeder::class);

        Livewire::test(MealCatalogTable::class)
            ->assertSee('₱420.00')
            ->assertSee('₱1,450.00')
            ->assertSee('Truffle Mushroom Risotto')
            ->assertSee('In Stock');
    }

    public function test_chef_can_toggle_86d_status_in_catalog_table(): void
    {
        $this->seed(MenuSeeder::class);
        $risotto = Meal::where('menu_code', 'M-01')->first();

        $this->assertTrue($risotto->is_available);

        Livewire::test(MealCatalogTable::class)
            ->call('toggleAvailability', $risotto->id);

        $this->assertFalse($risotto->fresh()->is_available);

        Livewire::test(MealCatalogTable::class)
            ->call('toggleAvailability', $risotto->id);

        $this->assertTrue($risotto->fresh()->is_available);
    }

    public function test_daily_specials_board_displays_limited_specials(): void
    {
        $this->seed(MenuSeeder::class);

        Livewire::test(DailySpecialsBoard::class)
            ->assertSee('Slow-Braised Lamb Shank')
            ->assertSee('left');
    }

    public function test_meal_form_creates_new_meal_in_database(): void
    {
        $category = MealCategory::create([
            'name' => 'Specials',
            'slug' => 'specials',
        ]);

        Livewire::test(\App\Domains\Menu\Livewire\MealForm::class)
            ->set('name', 'Grilled Salmon Bowl')
            ->set('menu_code', 'SP-99')
            ->set('category_id', $category->id)
            ->set('price', '450.00')
            ->set('cost_price', '150.00')
            ->set('description', 'Fresh salmon with seasoned quinoa.')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('meal-saved');

        $meal = Meal::where('menu_code', 'SP-99')->first();
        $this->assertNotNull($meal);
        $this->assertEquals('Grilled Salmon Bowl', $meal->name);
        $this->assertEquals('450.00', $meal->price);
    }

    public function test_meal_form_validates_required_fields(): void
    {
        Livewire::test(\App\Domains\Menu\Livewire\MealForm::class)
            ->set('name', '')
            ->set('category_id', null)
            ->set('price', '')
            ->call('save')
            ->assertHasErrors(['name', 'category_id', 'price']);
    }

    public function test_meal_catalog_table_searches_and_dispatches_edit(): void
    {
        $this->seed(MenuSeeder::class);
        $risotto = Meal::where('menu_code', 'M-01')->first();

        Livewire::test(MealCatalogTable::class)
            ->set('search', 'Risotto')
            ->assertSee('Truffle Mushroom Risotto')
            ->assertDontSee('Ribeye Steak')
            ->call('editMeal', $risotto->id)
            ->assertDispatched('edit-meal', id: $risotto->id);
    }

    public function test_meal_form_modal_open_and_close(): void
    {
        Livewire::test(\App\Domains\Menu\Livewire\MealForm::class)
            ->assertSet('isOpen', false)
            ->call('openNewMealModal')
            ->assertSet('isOpen', true)
            ->call('closeModal')
            ->assertSet('isOpen', false);
    }

    public function test_meal_catalog_table_can_delete_meal(): void
    {
        $this->seed(MenuSeeder::class);
        $risotto = Meal::where('menu_code', 'M-01')->first();

        Livewire::test(MealCatalogTable::class)
            ->call('deleteMeal', $risotto->id)
            ->assertDispatched('meal-saved');

        $this->assertSoftDeleted('meals', ['id' => $risotto->id]);
    }

    public function test_meal_form_responds_to_edit_meal_and_open_meal_modal_events(): void
    {
        $this->seed(MenuSeeder::class);
        $risotto = Meal::where('menu_code', 'M-01')->first();

        Livewire::test(\App\Domains\Menu\Livewire\MealForm::class)
            ->dispatch('edit-meal', id: $risotto->id)
            ->assertSet('mealId', $risotto->id)
            ->assertSet('name', 'Truffle Mushroom Risotto')
            ->assertDispatched('open-modal', 'meal-modal')
            ->dispatch('open-meal-modal')
            ->assertSet('mealId', null)
            ->assertSet('name', '')
            ->assertDispatched('open-modal', 'meal-modal');
    }

    public function test_create_meal_modal_creates_dish_via_domain_action(): void
    {
        \Illuminate\Support\Facades\Event::fake([\App\Domains\Menu\Events\MealCreated::class]);

        $category = MealCategory::create([
            'name' => 'Desserts',
            'slug' => 'desserts',
        ]);

        Livewire::test(\App\Domains\Menu\Livewire\CreateMealModal::class)
            ->set('name', 'Ube Cheesecake')
            ->set('menu_code', 'DES-01')
            ->set('category_id', $category->id)
            ->set('price', '220.00')
            ->set('description', 'Authentic ube cheesecake with latik.')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('close-modal', 'create-meal-modal')
            ->assertDispatched('meal-saved');

        $this->assertDatabaseHas('meals', [
            'name' => 'Ube Cheesecake',
            'menu_code' => 'DES-01',
            'price' => '220.00',
        ]);

        \Illuminate\Support\Facades\Event::assertDispatched(\App\Domains\Menu\Events\MealCreated::class);
    }

    public function test_edit_meal_modal_hydrates_and_updates_via_domain_action(): void
    {
        \Illuminate\Support\Facades\Event::fake([\App\Domains\Menu\Events\MealUpdated::class]);

        $this->seed(MenuSeeder::class);
        $risotto = Meal::where('menu_code', 'M-01')->first();

        Livewire::test(\App\Domains\Menu\Livewire\EditMealModal::class)
            ->dispatch('edit-meal', id: $risotto->id)
            ->assertSet('name', 'Truffle Mushroom Risotto')
            ->assertDispatched('open-modal', 'edit-meal-modal')
            ->set('price', '480.00')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('close-modal', 'edit-meal-modal')
            ->assertDispatched('meal-saved');

        $this->assertEquals('480.00', $risotto->fresh()->price);
        \Illuminate\Support\Facades\Event::assertDispatched(\App\Domains\Menu\Events\MealUpdated::class);
    }

    public function test_menu_domain_actions_and_events(): void
    {
        \Illuminate\Support\Facades\Event::fake([
            \App\Domains\Menu\Events\MealCreated::class,
            \App\Domains\Menu\Events\MealUpdated::class,
            \App\Domains\Menu\Events\MealAvailabilityToggled::class,
            \App\Domains\Menu\Events\MealDeleted::class,
        ]);

        $category = MealCategory::create([
            'name' => 'Beverages',
            'slug' => 'beverages',
        ]);

        $createAction = new \App\Domains\Menu\Actions\CreateMealAction();
        $meal = $createAction->handle([
            'name' => 'Calamansi Juice',
            'menu_code' => 'DRK-01',
            'category_id' => $category->id,
            'price' => 95.00,
        ]);

        \Illuminate\Support\Facades\Event::assertDispatched(\App\Domains\Menu\Events\MealCreated::class);

        $updateAction = new \App\Domains\Menu\Actions\UpdateMealAction();
        $updateAction->handle($meal, ['price' => 110.00]);
        \Illuminate\Support\Facades\Event::assertDispatched(\App\Domains\Menu\Events\MealUpdated::class);
        $this->assertEquals('110.00', $meal->fresh()->price);

        $toggleAction = new \App\Domains\Menu\Actions\ToggleMealAvailabilityAction();
        $toggleAction->handle($meal);
        \Illuminate\Support\Facades\Event::assertDispatched(\App\Domains\Menu\Events\MealAvailabilityToggled::class);
        $this->assertFalse($meal->fresh()->is_available);

        $deleteAction = new \App\Domains\Menu\Actions\DeleteMealAction();
        $deleteAction->handle($meal);
        \Illuminate\Support\Facades\Event::assertDispatched(\App\Domains\Menu\Events\MealDeleted::class);
        $this->assertSoftDeleted('meals', ['id' => $meal->id]);
    }
}



