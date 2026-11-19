<?php

namespace Tests\Feature\Menu;

use App\Domains\Branches\Models\Branch;
use App\Domains\Identity\Models\User;
use App\Domains\Menu\Actions\ToggleMealAvailabilityAction;
use App\Domains\Menu\Events\MealAvailabilityToggled;
use App\Domains\Menu\Livewire\CreateMealModal;
use App\Domains\Menu\Livewire\DailySpecialsBoard;
use App\Domains\Menu\Livewire\EditMealModal;
use App\Domains\Menu\Livewire\MealCatalogTable;
use App\Domains\Menu\Models\Meal;
use App\Domains\Menu\Models\MealBranchAvailability;
use App\Domains\Menu\Models\MealCategory;
use App\Domains\PointOfSale\Actions\CreateOrderAction;
use App\Domains\PointOfSale\Livewire\Checkout\CheckoutCart;
use App\Domains\PointOfSale\Livewire\MealCatalog as PosMealCatalog;
use App\Domains\PointOfSale\Livewire\ProductSearch;
use App\Filament\Resources\Meals\MealResource;
use App\Support\Enums\StaffRole;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

class ChefMenuCatalogBugsTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branch1;

    protected Branch $branch2;

    protected User $owner;

    protected User $superAdmin;

    protected User $manager1;

    protected User $manager2;

    protected User $chef1;

    protected User $chef2;

    protected User $cashier1;

    protected User $cashier2;

    protected MealCategory $mainsCategory;

    protected MealCategory $dessertsCategory;

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

        $this->owner = User::firstOrCreate(['email' => 'owner@easypos.com'], [
            'name' => 'System Owner',
            'password' => Hash::make('password'),
            'emp_id' => 'HQ-ADM-001',
            'branch_id' => null,
            'is_active' => true,
        ]);
        if (! $this->owner->hasRole(StaffRole::OWNER->value)) {
            $this->owner->assignRole(StaffRole::OWNER->value);
        }

        $this->superAdmin = User::create([
            'name' => 'HQ Super Admin',
            'email' => 'superadmin_menu_test@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'HQ-ADM-M01',
            'branch_id' => null,
            'is_active' => true,
        ]);
        $this->superAdmin->assignRole(StaffRole::SUPER_ADMIN->value);

        $this->manager1 = User::create([
            'name' => 'Branch 1 Manager',
            'email' => 'mgr1_menu_test@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'B01-MGR-M01',
            'branch_id' => $this->branch1->id,
            'is_active' => true,
        ]);
        $this->manager1->assignRole(StaffRole::BRANCH_MANAGER->value);

        $this->manager2 = User::create([
            'name' => 'Branch 2 Manager',
            'email' => 'mgr2_menu_test@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'B02-MGR-M02',
            'branch_id' => $this->branch2->id,
            'is_active' => true,
        ]);
        $this->manager2->assignRole(StaffRole::BRANCH_MANAGER->value);

        $this->chef1 = User::create([
            'name' => 'Chef Branch One',
            'email' => 'chef1_menu_test@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'B01-CHF-M01',
            'branch_id' => $this->branch1->id,
            'is_active' => true,
        ]);
        $this->chef1->assignRole(StaffRole::CHEF->value);

        $this->chef2 = User::create([
            'name' => 'Chef Branch Two',
            'email' => 'chef2_menu_test@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'B02-CHF-M02',
            'branch_id' => $this->branch2->id,
            'is_active' => true,
        ]);
        $this->chef2->assignRole(StaffRole::CHEF->value);

        $this->cashier1 = User::create([
            'name' => 'Cashier Branch One',
            'email' => 'cashier1_menu_test@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'B01-CSH-M01',
            'branch_id' => $this->branch1->id,
            'is_active' => true,
        ]);
        $this->cashier1->assignRole(StaffRole::CASHIER->value);

        $this->cashier2 = User::create([
            'name' => 'Cashier Branch Two',
            'email' => 'cashier2_menu_test@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'B02-CSH-M02',
            'branch_id' => $this->branch2->id,
            'is_active' => true,
        ]);
        $this->cashier2->assignRole(StaffRole::CASHIER->value);

        $this->mainsCategory = MealCategory::create([
            'name' => 'Mains',
            'slug' => 'mains',
            'is_active' => true,
            'display_order' => 1,
        ]);

        $this->dessertsCategory = MealCategory::create([
            'name' => 'Desserts',
            'slug' => 'desserts',
            'is_active' => true,
            'display_order' => 2,
        ]);

        $this->globalMeal = Meal::create([
            'branch_id' => null,
            'category_id' => $this->dessertsCategory->id,
            'name' => 'Creamy Leche Flan',
            'menu_code' => 'D-002',
            'price' => 150.00,
            'is_available' => true,
            'track_stock' => true,
            'stock_quantity' => 5,
            'is_active' => true,
        ]);

        $this->branch1Meal = Meal::create([
            'branch_id' => $this->branch1->id,
            'category_id' => $this->mainsCategory->id,
            'created_by' => $this->chef1->id,
            'name' => 'Branch One Sisig',
            'menu_code' => 'B1-001',
            'price' => 260.00,
            'is_available' => true,
            'is_active' => true,
        ]);

        $this->branch2Meal = Meal::create([
            'branch_id' => $this->branch2->id,
            'category_id' => $this->mainsCategory->id,
            'created_by' => $this->chef2->id,
            'name' => 'Branch Two Bulalo',
            'menu_code' => 'B2-001',
            'price' => 490.00,
            'is_available' => true,
            'is_active' => true,
        ]);
    }

    public function test_chef_can_mark_shared_dish_86d_and_complete_tab2_status_and_pos_pill_flow(): void
    {
        Cache::forever("branch_{$this->branch1->id}_menu_version", 1000);

        // Browser Tab 2 (/pos MealCatalog) is already open before Chef toggles in Tab 1
        $posCatalog = Livewire::actingAs($this->cashier1)
            ->test(PosMealCatalog::class);

        // UI shows HQ / Shared read-only badge on global dish (no Edit/Delete buttons),
        // while keeping Edit/Delete on Chef's own branch dish.
        $table = Livewire::actingAs($this->chef1)
            ->test(MealCatalogTable::class)
            ->assertSee('Creamy Leche Flan')
            ->assertSee('Global')
            ->assertDontSeeHtml("openDelete({$this->globalMeal->id},")
            ->assertDontSeeHtml("Livewire.dispatch('edit-meal', { id: {$this->globalMeal->id} })")
            ->assertSeeHtml("openDelete({$this->branch1Meal->id},")
            ->assertSeeHtml("Livewire.dispatch('edit-meal', { id: {$this->branch1Meal->id} })")
            ->assertSee('To temporarily mark any active dish unavailable');

        // Chef marks global dish 86'd on active menu (without deleting the meal record)
        $table->call('toggleAvailability', $this->globalMeal->id)
            ->assertDispatched('meal-saved')
            ->assertSee("86'd (Sold Out)");

        $this->assertNotSoftDeleted('meals', ['id' => $this->globalMeal->id]);
        $this->assertTrue($this->globalMeal->fresh()->is_available); // Global default stays intact
        $this->assertDatabaseHas('meal_branch_availabilities', [
            'meal_id' => $this->globalMeal->id,
            'branch_id' => $this->branch1->id,
            'is_available' => false,
        ]);

        // Daily Specials Board for Branch 1 reflects 1 dish 86'd and drops the 86'd dish from available specials
        Livewire::actingAs($this->chef1)
            ->test(DailySpecialsBoard::class)
            ->assertSee("1 dish 86'd", false)
            ->assertDontSee('Creamy Leche Flan');

        // Tab 2 ("In Stock") omits the 86'd dish while Tab 3 ("86'd") shows it
        $table->call('filterByStatus', 'in_stock')
            ->assertDontSee('Creamy Leche Flan')
            ->assertSee('Branch One Sisig');

        $table->call('filterByStatus', 'sold_out')
            ->assertSee('Creamy Leche Flan')
            ->assertDontSee('Branch One Sisig');

        // Browser Tab 2 (/pos MealCatalog) polls syncAvailability and updates the dish pill with wire:key & 86'd alert
        $posCatalog->call('syncAvailability')
            ->assertDispatched('meal-availability-synced')
            ->assertSeeHtml('wire:key="pos-meal-'.$this->globalMeal->id.'"')
            ->assertSee('Sold Out', false)
            ->assertSeeHtml('aria-disabled="true"')
            ->assertSee("Item \\'Creamy Leche Flan\\' is 86\\'d (Sold Out) and cannot be added.", false);
    }

    public function test_menu_page_includes_offline_error_toast_and_abort_hooks_without_optimistic_flip(): void
    {
        $response = $this->actingAs($this->chef1)->get('/menu');

        $response->assertOk()
            ->assertSee('Could not save availability. Please try again.', false)
            ->assertSee('x-on:menu-alert.window', false)
            ->assertSee('navigator.onLine', false)
            ->assertSee('new AbortController()', false)
            ->assertSee("window.addEventListener('offline'", false)
            ->assertSee("Livewire.hook('request'", false)
            ->assertSee("Livewire.hook('commit'", false)
            ->assertSee('wire:loading.attr="disabled"', false)
            ->assertSee('controller.abort(), 3000', false)
            ->assertDontSee('controller.abort(), 8000', false)
            ->assertSee('preventDefault()', false)
            ->assertSee('filamentErrorNotifications', false)
            ->assertSee('menu-toggle-settled', false)
            ->assertSee(':disabled="pending"', false)
            ->assertSeeHtml('wire:key="meal-availability-toggle-'.$this->globalMeal->id.'"')
            ->assertSeeHtml('data-available="1"');
    }

    public function test_failed_toggle_availability_leaves_database_and_ui_unchanged(): void
    {
        // Unauthorized user (Cashier) gets 403 on mount and leaves availability unchanged
        Livewire::actingAs($this->cashier1)
            ->test(MealCatalogTable::class)
            ->assertForbidden();

        // User with menu.view only can mount the table, but gets 403 on menu.manage actions
        $viewOnly = User::create([
            'name' => 'Menu Viewer B1',
            'email' => 'viewer1_menu_test@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'B01-VIW-M01',
            'branch_id' => $this->branch1->id,
            'is_active' => true,
        ]);
        $viewOnly->givePermissionTo('menu.view');

        Livewire::actingAs($viewOnly)
            ->test(MealCatalogTable::class)
            ->assertOk()
            ->call('toggleAvailability', $this->branch1Meal->id)
            ->assertForbidden();

        $this->assertTrue($this->branch1Meal->fresh()->is_available);
        $this->assertDatabaseCount('meal_branch_availabilities', 0);

        // Non-existent meal ID fails cleanly without partial writes
        try {
            Livewire::actingAs($this->chef1)
                ->test(MealCatalogTable::class)
                ->call('toggleAvailability', 999999);
            $this->fail('Expected ModelNotFoundException for non-existent meal ID.');
        } catch (ModelNotFoundException $e) {
            $this->assertStringContainsString('Meal', $e->getMessage());
        }

        $this->assertTrue($this->globalMeal->fresh()->is_available);
        $this->assertDatabaseCount('meal_branch_availabilities', 0);
    }

    public function test_broadcast_exception_does_not_fail_committed_availability_toggle(): void
    {
        Event::listen(MealAvailabilityToggled::class, function (): void {
            throw new \RuntimeException('Simulated Pusher/Reverb socket outage');
        });

        $this->actingAs($this->chef1);
        $action = new ToggleMealAvailabilityAction;
        $action->handle($this->globalMeal);

        $this->assertFalse($this->globalMeal->fresh()->isAvailableForBranch($this->branch1->id));
    }

    public function test_chef_cannot_open_edit_or_delete_global_or_other_branch_meals(): void
    {
        // Direct Livewire EditMealModal::loadMeal on global dish -> 403
        Livewire::actingAs($this->chef1)
            ->test(EditMealModal::class)
            ->dispatch('edit-meal', id: $this->globalMeal->id)
            ->assertStatus(403);

        // Direct Livewire EditMealModal::loadMeal on Branch 2 dish -> 403 (not 404)
        Livewire::actingAs($this->chef1)
            ->test(EditMealModal::class)
            ->dispatch('edit-meal', id: $this->branch2Meal->id)
            ->assertStatus(403);

        // Direct Livewire MealCatalogTable::editMeal on global dish -> 403
        Livewire::actingAs($this->chef1)
            ->test(MealCatalogTable::class)
            ->call('editMeal', $this->globalMeal->id)
            ->assertStatus(403);

        // Direct Livewire MealCatalogTable::deleteMeal on global dish -> 403, dish preserved
        Livewire::actingAs($this->chef1)
            ->test(MealCatalogTable::class)
            ->call('deleteMeal', $this->globalMeal->id)
            ->assertStatus(403);

        $this->assertNotSoftDeleted('meals', ['id' => $this->globalMeal->id]);

        // Direct Livewire MealCatalogTable::deleteMeal on Branch 2 dish -> 403, dish preserved
        Livewire::actingAs($this->chef1)
            ->test(MealCatalogTable::class)
            ->call('deleteMeal', $this->branch2Meal->id)
            ->assertStatus(403);

        $this->assertNotSoftDeleted('meals', ['id' => $this->branch2Meal->id]);
    }

    public function test_saving_unmodified_edit_modal_succeeds_with_soft_deleted_and_legacy_duplicate_menu_codes(): void
    {
        // Soft-deleted meal sharing the same menu_code as branch1Meal
        $trashedMeal = Meal::create([
            'branch_id' => $this->branch1->id,
            'category_id' => $this->mainsCategory->id,
            'name' => 'Old Archived Sisig',
            'menu_code' => 'B1-001',
            'price' => 200.00,
        ]);
        $trashedMeal->delete();

        // Legacy active duplicate meal sharing the same menu_code (simulating pre-unique Filament/seed data)
        Meal::create([
            'branch_id' => null,
            'category_id' => $this->mainsCategory->id,
            'name' => 'Legacy Duplicate Sisig',
            'menu_code' => 'B1-001',
            'price' => 250.00,
        ]);

        // Chef opens Branch 1 dish in EditMealModal and saves without changing menu_code
        $editModal = Livewire::actingAs($this->chef1)
            ->test(EditMealModal::class)
            ->dispatch('edit-meal', id: $this->branch1Meal->id)
            ->assertSet('menu_code', 'B1-001')
            ->assertSet('isOpen', true);

        // Changing category must NOT clobber existing non-empty menu_code
        $editModal->set('category_id', $this->dessertsCategory->id)
            ->assertSet('menu_code', 'B1-001')
            ->set('category_id', $this->mainsCategory->id)
            ->set('price', '275.00')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('close-modal', 'edit-meal-modal')
            ->assertDispatched('meal-saved');

        $this->assertEquals('275.00', $this->branch1Meal->fresh()->price);
        $this->assertEquals('B1-001', $this->branch1Meal->fresh()->menu_code);

        // Changing menu_code to a DIFFERENT active meal's code must still be rejected
        $otherActiveMeal = Meal::create([
            'branch_id' => $this->branch1->id,
            'category_id' => $this->mainsCategory->id,
            'name' => 'Other Active Dish',
            'menu_code' => 'B1-999',
            'price' => 180.00,
        ]);

        Livewire::actingAs($this->chef1)
            ->test(EditMealModal::class)
            ->dispatch('edit-meal', id: $this->branch1Meal->id)
            ->set('menu_code', '  b1-999 ')
            ->call('save')
            ->assertHasErrors(['menu_code']);

        $this->assertEquals('B1-001', $this->branch1Meal->fresh()->menu_code);
        $this->assertNotNull($otherActiveMeal->fresh());
    }

    public function test_edit_meal_modal_reset_and_create_meal_modal_ignores_soft_deleted_menu_code(): void
    {
        // Close resets mealId & fields cleanly, and calling save() with null mealId is safe
        Livewire::actingAs($this->chef1)
            ->test(EditMealModal::class)
            ->dispatch('edit-meal', id: $this->branch1Meal->id)
            ->assertSet('mealId', $this->branch1Meal->id)
            ->call('closeModal')
            ->assertSet('isOpen', false)
            ->assertSet('mealId', null)
            ->assertSet('name', '')
            ->call('save')
            ->assertDispatched('close-modal', 'edit-meal-modal');

        // Soft-deleted meal code can be reused in CreateMealModal
        $trashed = Meal::create([
            'branch_id' => $this->branch1->id,
            'category_id' => $this->mainsCategory->id,
            'name' => 'Retired Dish',
            'menu_code' => 'RET-01',
            'price' => 150.00,
        ]);
        $trashed->delete();

        Livewire::actingAs($this->chef1)
            ->test(CreateMealModal::class)
            ->set('name', 'Reborn Dish')
            ->set('menu_code', ' ret-01 ')
            ->set('category_id', $this->mainsCategory->id)
            ->set('price', '195.00')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('meals', [
            'name' => 'Reborn Dish',
            'menu_code' => 'RET-01',
            'branch_id' => $this->branch1->id,
            'deleted_at' => null,
        ]);
    }

    public function test_global_dish_86d_by_chef_is_strictly_isolated_to_chefs_branch(): void
    {
        Cache::forever("branch_{$this->branch1->id}_menu_version", 1000);
        Cache::forever("branch_{$this->branch2->id}_menu_version", 2000);
        Cache::forever('branch_global_menu_version', 3000);

        // Branch 1 Chef 86's the shared global dish
        Livewire::actingAs($this->chef1)
            ->test(MealCatalogTable::class)
            ->call('toggleAvailability', $this->globalMeal->id);

        // Global row & other branch cache versions remain untouched
        $this->assertTrue($this->globalMeal->fresh()->is_available);
        $this->assertGreaterThan(1000, Cache::get("branch_{$this->branch1->id}_menu_version"));
        $this->assertEquals(2000, Cache::get("branch_{$this->branch2->id}_menu_version"));
        $this->assertEquals(3000, Cache::get('branch_global_menu_version'));

        // Branch 1 /menu sees it as 86'd; Branch 2 /menu still sees it as In Stock
        Livewire::actingAs($this->chef1)
            ->test(MealCatalogTable::class)
            ->call('filterByStatus', 'sold_out')
            ->assertSee('Creamy Leche Flan');

        Livewire::actingAs($this->chef2)
            ->test(MealCatalogTable::class)
            ->call('filterByStatus', 'in_stock')
            ->assertSee('Creamy Leche Flan');

        Livewire::actingAs($this->chef2)
            ->test(DailySpecialsBoard::class)
            ->assertDontSee("dish 86'd", false)
            ->assertSee('Creamy Leche Flan');

        // Branch 1 POS, CheckoutCart, and ProductSearch reject it; Branch 2 accepts it
        Livewire::actingAs($this->cashier1)
            ->test(PosMealCatalog::class)
            ->assertViewHas('soldOutCount', 1)
            ->assertSeeHtml('aria-disabled="true"')
            ->assertSee("Item \\'Creamy Leche Flan\\' is 86\\'d (Sold Out) and cannot be added.", false);

        Livewire::actingAs($this->cashier2)
            ->test(PosMealCatalog::class)
            ->assertViewHas('soldOutCount', 0)
            ->assertDontSeeHtml('aria-disabled="true"')
            ->assertDontSee("Item \\'Creamy Leche Flan\\' is 86\\'d (Sold Out) and cannot be added.", false);

        Livewire::actingAs($this->cashier1)
            ->test(CheckoutCart::class)
            ->dispatch('add-to-cart', mealId: $this->globalMeal->id)
            ->assertCount('items', 0)
            ->assertDispatched('pos-alert');

        Livewire::actingAs($this->cashier2)
            ->test(CheckoutCart::class)
            ->dispatch('add-to-cart', mealId: $this->globalMeal->id)
            ->assertCount('items', 1);

        Livewire::actingAs($this->cashier1)
            ->test(ProductSearch::class)
            ->set('query', 'D-002')
            ->call('scanOrSubmit')
            ->assertNotDispatched('add-to-cart')
            ->assertDispatched('pos-alert');

        Livewire::actingAs($this->cashier2)
            ->test(ProductSearch::class)
            ->set('query', 'D-002')
            ->call('scanOrSubmit')
            ->assertDispatched('add-to-cart', mealId: $this->globalMeal->id);

        // CreateOrderAction blocks Branch 1 checkout and allows Branch 2 checkout
        $orderAction = app(CreateOrderAction::class);

        try {
            $orderAction->execute([
                'branch_id' => $this->branch1->id,
                'cashier_id' => $this->cashier1->id,
                'order_type' => 'dine_in',
                'items' => [['meal_id' => $this->globalMeal->id, 'quantity' => 1]],
            ]);
            $this->fail('Expected InvalidArgumentException when ordering branch-86d global meal in Branch 1.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString("86'd / Sold Out", $e->getMessage());
        }

        $branch2Order = $orderAction->execute([
            'branch_id' => $this->branch2->id,
            'cashier_id' => $this->cashier2->id,
            'order_type' => 'dine_in',
            'items' => [['meal_id' => $this->globalMeal->id, 'quantity' => 1]],
        ]);
        $this->assertDatabaseHas('orders', [
            'id' => $branch2Order->id,
            'branch_id' => $this->branch2->id,
        ]);
    }

    public function test_chef_created_meal_visibility_and_editability_across_branches_owner_menu_and_admin_backoffice(): void
    {
        // 1. Branch 1 Chef creates a new dish
        Livewire::actingAs($this->chef1)
            ->test(CreateMealModal::class)
            ->set('name', 'Chef One SpecialSinigang')
            ->set('menu_code', 'C1-777')
            ->set('category_id', $this->mainsCategory->id)
            ->set('price', '310.00')
            ->call('save')
            ->assertHasNoErrors();

        $createdMeal = Meal::where('menu_code', 'C1-777')->firstOrFail();
        $this->assertEquals($this->branch1->id, $createdMeal->branch_id);

        // 2. Hidden from Branch 2 Chef /menu and Branch 2 Cashier /pos
        Livewire::actingAs($this->chef2)
            ->test(MealCatalogTable::class)
            ->assertDontSee('Chef One SpecialSinigang');

        Livewire::actingAs($this->cashier2)
            ->test(PosMealCatalog::class)
            ->assertDontSee('Chef One SpecialSinigang');

        // 3. Visible on /menu to Owner (non-branch-scoped) with branch name badge, and editable without 404/403
        Livewire::actingAs($this->owner)
            ->test(MealCatalogTable::class)
            ->assertSee('Chef One SpecialSinigang')
            ->assertSee('Branch One')
            ->call('editMeal', $createdMeal->id)
            ->assertDispatched('edit-meal', id: $createdMeal->id);

        Livewire::actingAs($this->owner)
            ->test(EditMealModal::class)
            ->dispatch('edit-meal', id: $createdMeal->id)
            ->assertSet('mealId', $createdMeal->id)
            ->set('price', '335.00')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertEquals('335.00', $createdMeal->fresh()->price);

        // 4. Visible and modifiable in Filament /admin/meals for both Super Admin and Owner
        $this->actingAs($this->superAdmin);
        $this->assertTrue(MealResource::getEloquentQuery()->whereKey($createdMeal->id)->exists());
        $this->assertTrue(MealResource::canEdit($createdMeal));

        $this->actingAs($this->owner);
        $this->assertTrue(MealResource::getEloquentQuery()->whereKey($createdMeal->id)->exists());
        $this->assertTrue(MealResource::canEdit($createdMeal));

        // 5. Creating Chef (Branch 1) and Branch 1 Manager can modify it on /menu
        Livewire::actingAs($this->chef1)
            ->test(EditMealModal::class)
            ->dispatch('edit-meal', id: $createdMeal->id)
            ->set('price', '345.00')
            ->call('save')
            ->assertHasNoErrors();
        $this->assertEquals('345.00', $createdMeal->fresh()->price);

        Livewire::actingAs($this->manager1)
            ->test(EditMealModal::class)
            ->dispatch('edit-meal', id: $createdMeal->id)
            ->set('price', '355.00')
            ->call('save')
            ->assertHasNoErrors();
        $this->assertEquals('355.00', $createdMeal->fresh()->price);

        // 6. Branch 2 Chef and Branch 2 Manager are strictly denied (403)
        Livewire::actingAs($this->chef2)
            ->test(EditMealModal::class)
            ->dispatch('edit-meal', id: $createdMeal->id)
            ->assertStatus(403);

        Livewire::actingAs($this->manager2)
            ->test(EditMealModal::class)
            ->dispatch('edit-meal', id: $createdMeal->id)
            ->assertStatus(403);

        $this->assertEquals('355.00', $createdMeal->fresh()->price);
    }

    public function test_meal_catalog_table_and_pos_catalog_avoid_n_plus_one_queries_for_branch_overrides(): void
    {
        for ($i = 1; $i <= 15; $i++) {
            $m = Meal::create([
                'branch_id' => null,
                'category_id' => $this->mainsCategory->id,
                'name' => "Global Dish {$i}",
                'menu_code' => sprintf('GLB-%03d', $i),
                'price' => 100 + $i,
                'is_available' => true,
                'is_active' => true,
            ]);

            MealBranchAvailability::create([
                'meal_id' => $m->id,
                'branch_id' => $this->branch1->id,
                'is_available' => $i % 2 === 0,
            ]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        Livewire::actingAs($this->chef1)->test(MealCatalogTable::class)->assertOk();
        $menuOverrideQueries = collect(DB::getQueryLog())->filter(
            fn ($q) => str_contains(strtolower($q['query']), 'meal_branch_availabilities')
        );
        $this->assertLessThanOrEqual(1, $menuOverrideQueries->count(), 'MealCatalogTable must eager-load branchAvailabilities in a single query.');

        DB::flushQueryLog();
        Livewire::actingAs($this->cashier1)->test(PosMealCatalog::class)->assertOk();
        $posOverrideQueries = collect(DB::getQueryLog())->filter(
            fn ($q) => str_contains(strtolower($q['query']), 'meal_branch_availabilities')
        );
        $this->assertLessThanOrEqual(1, $posOverrideQueries->count(), 'POS MealCatalog must eager-load branchAvailabilities in a single query.');
    }

    public function test_chef_can_modify_portions_on_shared_dish_scoped_to_own_branch_only(): void
    {
        $origTrack = (bool) $this->globalMeal->track_stock;
        $origQty = $this->globalMeal->stock_quantity;

        // Chef in Branch 1 adjusts portions for shared HQ dish
        Livewire::actingAs($this->chef1)
            ->test(MealCatalogTable::class)
            ->call('openPortionsModal', $this->globalMeal->id)
            ->assertDispatched('open-modal', 'branch-portions-modal')
            ->set('portionsTrackStock', true)
            ->set('portionsQuantity', 7)
            ->call('savePortions')
            ->assertHasNoErrors()
            ->assertDispatched('close-modal', 'branch-portions-modal')
            ->assertDispatched('meal-saved');

        // Shared meal itself and Branch 2 remain untouched
        $freshShared = $this->globalMeal->fresh();
        $this->assertSame($origTrack, (bool) $freshShared->track_stock);
        $this->assertSame($origQty, $freshShared->stock_quantity);
        $this->assertTrue($freshShared->trackStockForBranch($this->branch1->id));
        $this->assertSame(7, $freshShared->stockQuantityForBranch($this->branch1->id));
        $this->assertSame($origTrack, $freshShared->trackStockForBranch($this->branch2->id));
        $this->assertSame($origTrack ? (int) $origQty : null, $freshShared->stockQuantityForBranch($this->branch2->id));

        // Branch 1 POS sees 7 portions left; Branch 2 POS is unaffected
        Livewire::actingAs($this->cashier1)
            ->test(PosMealCatalog::class)
            ->assertSee('7 left');

        // Chef in Branch 1 cannot adjust portions for a Branch 2-owned dish
        Livewire::actingAs($this->chef1)
            ->test(MealCatalogTable::class)
            ->call('openPortionsModal', $this->branch2Meal->id)
            ->assertForbidden();
    }
}
