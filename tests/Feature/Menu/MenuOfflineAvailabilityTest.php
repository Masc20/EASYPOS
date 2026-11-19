<?php

namespace Tests\Feature\Menu;

use App\Domains\Branches\Models\Branch;
use App\Domains\Identity\Models\User;
use App\Domains\Menu\Actions\ToggleMealAvailabilityAction;
use App\Domains\Menu\Livewire\MealCatalogTable;
use App\Domains\Menu\Models\Meal;
use App\Domains\Menu\Models\MealCategory;
use App\Support\Enums\StaffRole;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class MenuOfflineAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branch1;

    protected Branch $branch2;

    protected User $chef1;

    protected User $manager1;

    protected User $cashier1;

    protected MealCategory $category;

    protected Meal $globalMeal;

    protected Meal $branch1Meal;

    protected Meal $branch2Meal;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->branch1 = Branch::firstOrCreate(['code' => 'B01'], [
            'name' => 'Branch One',
            'is_active' => true,
        ]);

        $this->branch2 = Branch::firstOrCreate(['code' => 'B02'], [
            'name' => 'Branch Two',
            'is_active' => true,
        ]);

        $this->chef1 = User::create([
            'name' => 'Chef One',
            'email' => 'chef1_offline_test@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'B01-CHF-901',
            'branch_id' => $this->branch1->id,
            'is_active' => true,
        ]);
        $this->chef1->assignRole(StaffRole::CHEF->value);

        $this->manager1 = User::create([
            'name' => 'Manager One',
            'email' => 'mgr1_offline_test@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'B01-MGR-901',
            'branch_id' => $this->branch1->id,
            'is_active' => true,
        ]);
        $this->manager1->assignRole(StaffRole::BRANCH_MANAGER->value);

        $this->cashier1 = User::create([
            'name' => 'Cashier One',
            'email' => 'csh1_offline_test@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'B01-CSH-901',
            'branch_id' => $this->branch1->id,
            'is_active' => true,
        ]);
        $this->cashier1->assignRole(StaffRole::CASHIER->value);

        $this->category = MealCategory::create([
            'name' => 'Mains',
            'slug' => 'mains',
            'is_active' => true,
            'display_order' => 1,
        ]);

        $this->globalMeal = Meal::create([
            'branch_id' => null,
            'category_id' => $this->category->id,
            'name' => 'Global Adobo',
            'menu_code' => 'G-101',
            'price' => 220.00,
            'is_available' => true,
            'is_active' => true,
        ]);

        $this->branch1Meal = Meal::create([
            'branch_id' => $this->branch1->id,
            'category_id' => $this->category->id,
            'created_by' => $this->chef1->id,
            'name' => 'Branch One Pork Chop',
            'menu_code' => 'B1-101',
            'price' => 280.00,
            'is_available' => true,
            'is_active' => true,
        ]);

        $this->branch2Meal = Meal::create([
            'branch_id' => $this->branch2->id,
            'category_id' => $this->category->id,
            'name' => 'Branch Two Kare-Kare',
            'menu_code' => 'B2-101',
            'price' => 350.00,
            'is_available' => true,
            'is_active' => true,
        ]);
    }

    public function test_authorized_chef_and_manager_can_toggle_in_stock_and_86d_with_branch_isolation_and_version_bump(): void
    {
        Cache::forever("branch_{$this->branch1->id}_menu_version", 100);
        Cache::forever("branch_{$this->branch2->id}_menu_version", 200);

        $table = Livewire::actingAs($this->chef1)
            ->test(MealCatalogTable::class)
            ->assertSee('In Stock')
            ->assertSeeHtml('wire:key="meal-availability-toggle-'.$this->branch1Meal->id.'"')
            ->assertSeeHtml('data-available="1"');

        // Toggle In Stock -> 86'd (Sold Out)
        $table->call('toggleAvailability', $this->branch1Meal->id)
            ->assertDispatched('meal-saved')
            ->assertSee("86'd (Sold Out)")
            ->assertSeeHtml('data-meal-id="'.$this->branch1Meal->id.'"')
            ->assertSeeHtml('data-available="0"');

        $this->assertFalse((bool) $this->branch1Meal->fresh()->is_available);
        $this->assertGreaterThan(100, Cache::get("branch_{$this->branch1->id}_menu_version"));
        $this->assertSame(200, Cache::get("branch_{$this->branch2->id}_menu_version"));

        // Toggle back 86'd (Sold Out) -> In Stock
        $table->call('toggleAvailability', $this->branch1Meal->id)
            ->assertDispatched('meal-saved')
            ->assertSee('In Stock');

        $this->assertTrue((bool) $this->branch1Meal->fresh()->is_available);

        // Branch Manager toggles shared global meal for Branch 1 only
        Livewire::actingAs($this->manager1)
            ->test(MealCatalogTable::class)
            ->call('toggleAvailability', $this->globalMeal->id)
            ->assertDispatched('meal-saved');

        $this->assertTrue((bool) $this->globalMeal->fresh()->is_available);
        $this->assertFalse($this->globalMeal->fresh()->isAvailableForBranch($this->branch1->id));
        $this->assertTrue($this->globalMeal->fresh()->isAvailableForBranch($this->branch2->id));
    }

    public function test_guest_unauthorized_cashier_and_wrong_branch_actors_are_denied_without_side_effects(): void
    {
        Cache::forever("branch_{$this->branch1->id}_menu_version", 100);
        Cache::forever("branch_{$this->branch2->id}_menu_version", 200);

        $this->get('/menu')->assertRedirect();

        $this->actingAs($this->cashier1)
            ->get('/menu')
            ->assertForbidden();

        Livewire::actingAs($this->cashier1)
            ->test(MealCatalogTable::class)
            ->assertForbidden();

        $viewOnly = User::create([
            'name' => 'Menu Viewer One',
            'email' => 'viewer1_offline_test@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'B01-VIW-901',
            'branch_id' => $this->branch1->id,
            'is_active' => true,
        ]);
        $viewOnly->givePermissionTo('menu.view');

        Livewire::actingAs($viewOnly)
            ->test(MealCatalogTable::class)
            ->assertOk()
            ->call('toggleAvailability', $this->branch1Meal->id)
            ->assertForbidden();

        Livewire::actingAs($this->chef1)
            ->test(MealCatalogTable::class)
            ->call('toggleAvailability', $this->branch2Meal->id)
            ->assertStatus(403);

        Livewire::actingAs($this->manager1)
            ->test(MealCatalogTable::class)
            ->call('toggleAvailability', $this->branch2Meal->id)
            ->assertStatus(403);

        $this->assertTrue((bool) $this->branch1Meal->fresh()->is_available);
        $this->assertTrue((bool) $this->branch2Meal->fresh()->is_available);
        $this->assertDatabaseCount('meal_branch_availabilities', 0);
        $this->assertSame(100, Cache::get("branch_{$this->branch1->id}_menu_version"));
        $this->assertSame(200, Cache::get("branch_{$this->branch2->id}_menu_version"));
    }

    public function test_failed_toggle_keeps_dish_in_stock_and_allows_immediate_retry_upon_recovery(): void
    {
        Cache::forever("branch_{$this->branch1->id}_menu_version", 500);

        $table = Livewire::actingAs($this->chef1)
            ->test(MealCatalogTable::class);

        // Missing meal ID leaves database and cache untouched
        try {
            $table->call('toggleAvailability', 999999);
            $this->fail('Expected ModelNotFoundException for missing meal ID.');
        } catch (ModelNotFoundException) {
            // Expected
        }

        // Simulate transient server/connectivity failure during ToggleMealAvailabilityAction
        $this->app->instance(ToggleMealAvailabilityAction::class, new class extends ToggleMealAvailabilityAction
        {
            public function handle(Meal|int $meal): Meal
            {
                throw new RuntimeException('Simulated connection drop');
            }
        });

        try {
            $table->call('toggleAvailability', $this->branch1Meal->id);
            $this->fail('Expected RuntimeException during simulated failure.');
        } catch (RuntimeException $e) {
            $this->assertSame('Simulated connection drop', $e->getMessage());
        }

        // State remains In Stock (unchanged) with no side effects
        $this->assertTrue((bool) $this->branch1Meal->fresh()->is_available);
        $this->assertDatabaseCount('meal_branch_availabilities', 0);
        $this->assertSame(500, Cache::get("branch_{$this->branch1->id}_menu_version"));
        $table->assertSee('In Stock')
            ->assertSeeHtml('data-available="1"');

        // Connectivity recovers: immediate retry succeeds cleanly
        $this->app->instance(ToggleMealAvailabilityAction::class, new ToggleMealAvailabilityAction);

        $table->call('toggleAvailability', $this->branch1Meal->id)
            ->assertDispatched('meal-saved')
            ->assertSee("86'd (Sold Out)")
            ->assertSeeHtml('data-available="0"');

        $this->assertFalse((bool) $this->branch1Meal->fresh()->is_available);
        $this->assertGreaterThan(500, Cache::get("branch_{$this->branch1->id}_menu_version"));
    }

    public function test_menu_page_renders_offline_feedback_toast_dedup_filament_isolation_and_non_leaking_hooks(): void
    {
        $response = $this->actingAs($this->chef1)->get('/menu');
        $html = $response->getContent();

        $response->assertOk()
            ->assertSee('Could not save availability. Please try again.', false)
            ->assertSee('x-on:menu-alert.window', false)
            ->assertSee('clearTimeout(t.timeoutId)', false)
            ->assertSee('this.toasts.length >= 4', false)
            ->assertSee("window.location.pathname.startsWith('/menu')", false)
            ->assertSee('window.filamentErrorNotifications = undefined', false)
            ->assertSee('livewire:navigated', false)
            ->assertSee('new AbortController()', false)
            ->assertSee('setTimeout(() => controller.abort(), 3000)', false)
            ->assertDontSee('setTimeout(() => controller.abort(), 8000)', false)
            ->assertSee('if (!navigator.onLine)', false)
            ->assertSee('preventDefault()', false)
            ->assertSee('menu-toggle-settled', false)
            ->assertSee(':disabled="pending"', false)
            ->assertSee('wire:loading.attr="disabled"', false)
            ->assertDontSee('await $wire.toggleAvailability', false);

        // Verify the table pill itself preserves server-confirmed In Stock text without optimistic flip
        $tableHtml = file_get_contents(resource_path('views/livewire/menu/meal-catalog-table.blade.php'));
        $this->assertIsString($tableHtml);
        $this->assertStringNotContainsString('optimisticAvailable', $tableHtml);
        $this->assertStringNotContainsString('await $wire.toggleAvailability', $tableHtml);
        $this->assertStringContainsString('wire:key="meal-availability-toggle-{{ $meal->id }}"', $tableHtml);
        $this->assertStringContainsString("{{ \$isAvailable ? 'In Stock' : '86\\'d (Sold Out)' }}", $tableHtml);
        $this->assertStringContainsString('x-on:menu-toggle-settled.window', $html);
    }
}
