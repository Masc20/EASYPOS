<?php

namespace Tests\Feature\Security;

use App\Domains\Branches\Models\Branch;
use App\Domains\Identity\Models\User;
use App\Domains\Menu\Actions\CreateMealAction;
use App\Domains\Menu\Actions\DeleteMealAction;
use App\Domains\Menu\Actions\UpdateBranchPortionsAction;
use App\Domains\Menu\Events\MealAvailabilityToggled;
use App\Domains\Menu\Livewire\CreateMealModal;
use App\Domains\Menu\Livewire\EditMealModal;
use App\Domains\Menu\Livewire\MealCatalogTable;
use App\Domains\Menu\Livewire\MealForm as LivewireMealForm;
use App\Domains\Menu\Models\Meal;
use App\Domains\Menu\Models\MealBranchAvailability;
use App\Domains\Menu\Models\MealCategory;
use App\Domains\PointOfSale\Livewire\MealCatalog as PosMealCatalog;
use App\Domains\PointOfSale\Models\Order;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\SalesRecords;
use App\Filament\Resources\MealCategories\MealCategoryResource;
use App\Filament\Resources\MealCategories\Pages\ListMealCategories;
use App\Filament\Resources\Meals\MealResource;
use App\Filament\Resources\Meals\Pages\ListMeals;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Widgets\DailySalesRevenueChart;
use App\Support\Enums\OrderStatus;
use App\Support\Enums\PaymentStatus;
use App\Support\Enums\StaffRole;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class BranchIsolationMenuTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branch1;

    protected Branch $branch2;

    protected User $owner;

    protected User $superAdmin;

    protected User $manager1;

    protected User $manager2;

    protected User $nullBranchManager;

    protected User $nullBranchChef;

    protected User $chef1;

    protected User $cashier1;

    protected User $cashier2;

    protected User $cook1;

    protected MealCategory $globalCat;

    protected MealCategory $branch1Cat;

    protected MealCategory $branch2Cat;

    protected Meal $globalMeal;

    protected Meal $branch1Meal;

    protected Meal $branch2Meal;

    protected Meal $branch2InGlobalCatMeal;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->branch1 = Branch::firstOrCreate(['code' => 'B01'], ['name' => 'Branch One', 'is_active' => true]);
        $this->branch2 = Branch::firstOrCreate(['code' => 'B02'], ['name' => 'Branch Two', 'is_active' => true]);

        $this->owner = User::firstOrCreate(['email' => 'owner@easypos.com'], [
            'name' => 'Owner', 'password' => Hash::make('password'), 'emp_id' => 'HQ-ADM-001', 'is_active' => true,
        ]);
        $this->owner->assignRole(StaffRole::OWNER->value);

        $this->superAdmin = $this->makeUser('Super Admin', 'sa_m1@test.local', 'HQ-SA-M1', null, StaffRole::SUPER_ADMIN);
        $this->manager1 = $this->makeUser('Mgr 1', 'mgr1_m1@test.local', 'B01-MGR-M1', $this->branch1->id, StaffRole::BRANCH_MANAGER);
        $this->manager2 = $this->makeUser('Mgr 2', 'mgr2_m1@test.local', 'B02-MGR-M1', $this->branch2->id, StaffRole::BRANCH_MANAGER);
        $this->nullBranchManager = $this->makeUser('Null Mgr', 'nullmgr_m1@test.local', 'HQ-NMGR-M1', null, StaffRole::BRANCH_MANAGER);
        $this->nullBranchChef = $this->makeUser('Null Chef', 'nullchef_m1@test.local', 'HQ-NCHF-M1', null, StaffRole::CHEF);
        $this->chef1 = $this->makeUser('Chef 1', 'chef1_m1@test.local', 'B01-CHF-M1', $this->branch1->id, StaffRole::CHEF);
        $this->cashier1 = $this->makeUser('Cashier 1', 'csh1_m1@test.local', 'B01-CSH-M1', $this->branch1->id, StaffRole::CASHIER);
        $this->cashier2 = $this->makeUser('Cashier 2', 'csh2_m1@test.local', 'B02-CSH-M1', $this->branch2->id, StaffRole::CASHIER);
        $this->cook1 = $this->makeUser('Cook 1', 'cook1_m1@test.local', 'B01-COK-M1', $this->branch1->id, StaffRole::COOK);

        $this->globalCat = MealCategory::create(['branch_id' => null, 'name' => 'Global Mains', 'slug' => 'global-mains', 'is_active' => true, 'display_order' => 1]);
        $this->branch1Cat = MealCategory::create(['branch_id' => $this->branch1->id, 'name' => 'B1 Specials', 'slug' => 'b1-specials', 'is_active' => true, 'display_order' => 2]);
        $this->branch2Cat = MealCategory::create(['branch_id' => $this->branch2->id, 'name' => 'B2 Specials', 'slug' => 'b2-specials', 'is_active' => true, 'display_order' => 3]);

        $this->globalMeal = Meal::create(['branch_id' => null, 'category_id' => $this->globalCat->id, 'name' => 'Global Adobo', 'menu_code' => 'GLB-M1', 'price' => 180.00, 'is_available' => true, 'is_active' => true]);
        $this->branch1Meal = Meal::create(['branch_id' => $this->branch1->id, 'category_id' => $this->branch1Cat->id, 'name' => 'B1 Sisig', 'menu_code' => 'B1-M1', 'price' => 220.00, 'is_available' => true, 'is_active' => true]);
        $this->branch2Meal = Meal::create(['branch_id' => $this->branch2->id, 'category_id' => $this->branch2Cat->id, 'name' => 'B2 Lechon', 'menu_code' => 'B2-M1', 'price' => 350.00, 'is_available' => true, 'is_active' => true]);
        $this->branch2InGlobalCatMeal = Meal::create(['branch_id' => $this->branch2->id, 'category_id' => $this->globalCat->id, 'name' => 'B2 Global Cat Dish', 'menu_code' => 'B2-GM1', 'price' => 290.00, 'is_available' => true, 'is_active' => true]);
    }

    public function test_branch_removal_preserves_global_record_and_other_branch_visibility(): void
    {
        foreach ([$this->chef1, $this->manager1] as $actor) {
            MealBranchAvailability::query()->delete();
            Livewire::actingAs($actor)->test(MealCatalogTable::class)
                ->call('deleteMeal', $this->globalMeal->id)
                ->assertSuccessful();
            $this->assertNotSoftDeleted('meals', ['id' => $this->globalMeal->id]);
            $this->assertTrue(Meal::whereKey($this->globalMeal->id)->exists());
            $this->assertFalse(Meal::forBranch($this->branch1->id)->whereKey($this->globalMeal->id)->exists());
            $this->assertTrue(Meal::forBranch($this->branch2->id)->whereKey($this->globalMeal->id)->exists());
            $this->assertFalse($this->globalMeal->fresh()->isAvailableForBranch($this->branch1->id));
            $this->assertTrue($this->globalMeal->fresh()->isAvailableForBranch($this->branch2->id));
            // Repeated removal remains a single branch override.
            app(DeleteMealAction::class)->handle($this->globalMeal->id);
            $this->assertSame(1, MealBranchAvailability::count());
        }
    }

    public function test_branch_removal_rejects_foreign_branch_and_unprivileged_actors(): void
    {
        foreach ([$this->chef1, $this->manager1, $this->cashier1, $this->nullBranchChef] as $actor) {
            $this->actingAs($actor);
            try {
                app(DeleteMealAction::class)->handle($this->branch2Meal->id);
                $this->fail('Removal should have been denied.');
            } catch (HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
            }
            $this->assertNotSoftDeleted('meals', ['id' => $this->branch2Meal->id]);
            $this->assertSame(0, MealBranchAvailability::count());
        }
    }

    public function test_branch_owned_meal_removal_preserves_the_owner_catalog(): void
    {
        Livewire::actingAs($this->chef1)->test(MealCatalogTable::class)
            ->call('deleteMeal', $this->branch1Meal->id)->assertSuccessful();
        $this->assertNotSoftDeleted('meals', ['id' => $this->branch1Meal->id]);
        $this->assertFalse($this->branch1Meal->fresh()->isAvailableForBranch($this->branch1->id));
        $this->actingAs($this->owner);
        $this->assertTrue(MealResource::getEloquentQuery()->whereKey($this->branch1Meal->id)->exists());
        app(DeleteMealAction::class)->handle($this->branch1Meal->id);
        $this->assertSoftDeleted('meals', ['id' => $this->branch1Meal->id]);
    }

    public function test_admin_bulk_removal_hides_shared_and_branch_meals_only_for_the_actor_branch(): void
    {
        foreach ([$this->manager1, $this->chef1] as $actor) {
            MealBranchAvailability::query()->delete();
            $actor->givePermissionTo('admin.access');
            Livewire::actingAs($actor)->test(ListMeals::class)
                ->selectTableRecords([$this->globalMeal->id, $this->branch1Meal->id])
                ->callAction(TestAction::make(DeleteBulkAction::class)->table()->bulk())
                ->assertHasNoActionErrors();
            foreach ([$this->globalMeal, $this->branch1Meal] as $meal) {
                $this->assertNotSoftDeleted('meals', ['id' => $meal->id]);
                $this->assertFalse(Meal::forBranch($this->branch1->id)->whereKey($meal->id)->exists());
            }
            $this->assertTrue(Meal::forBranch($this->branch2->id)->whereKey($this->globalMeal->id)->exists());
        }
    }

    private function makeUser(string $name, string $email, string $empId, ?int $branchId, StaffRole $role): User
    {
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make('password'),
            'emp_id' => $empId,
            'pin_code' => Hash::make('1234'),
            'branch_id' => $branchId,
            'is_active' => true,
        ]);
        $user->assignRole($role->value);

        return $user;
    }

    public function test_non_executive_with_null_branch_id_is_still_branch_scoped_and_blocked_from_branches_and_global_mutations(): void
    {
        $this->assertTrue($this->nullBranchManager->isBranchScoped());
        $this->assertTrue($this->nullBranchChef->isBranchScoped());
        $this->assertSame(-1, $this->nullBranchManager->effectiveBranchId(null));
        $this->assertSame(-1, $this->nullBranchManager->effectiveBranchId($this->branch1->id));
        $this->assertFalse($this->nullBranchManager->canAccessAdmin());

        $this->assertFalse($this->owner->isBranchScoped());
        $this->assertFalse($this->superAdmin->isBranchScoped());
        $this->assertNull($this->owner->effectiveBranchId(null));
        $this->assertSame($this->branch2->id, $this->superAdmin->effectiveBranchId($this->branch2->id));

        Order::create([
            'order_number' => 'B01-NULL-001', 'branch_id' => $this->branch1->id, 'cashier_id' => $this->cashier1->id,
            'status' => OrderStatus::COMPLETED->value, 'payment_status' => PaymentStatus::PAID->value,
            'order_type' => 'dine_in', 'subtotal' => 250.00, 'total_amount' => 250.00,
        ]);

        $this->actingAs($this->nullBranchManager);
        $this->assertFalse(MealResource::canAccess());
        $this->assertFalse(MealResource::canCreate());
        $this->assertFalse(MealResource::canEdit($this->branch1Meal));
        $this->assertFalse(MealResource::canEdit($this->globalMeal));
        $this->assertFalse(MealResource::canDelete($this->branch1Meal));
        $this->assertFalse(MealResource::getEloquentQuery()->where('branch_id', $this->branch1->id)->exists());
        $this->assertFalse(MealCategoryResource::canCreate());
        $this->assertFalse(MealCategoryResource::canEdit($this->branch1Cat));
        $this->assertFalse(MealCategoryResource::canDelete($this->branch1Cat));
        $this->assertSame(0, OrderResource::getEloquentQuery()->count());
        $this->assertFalse(SalesRecords::canAccess());

        $this->get('/admin/meals')->assertStatus(403);
        $this->get('/admin/sales-records')->assertStatus(403);

        $beforeCount = Meal::count();
        try {
            (new CreateMealAction)->handle(['name' => 'Rogue Null Dish', 'category_id' => $this->globalCat->id, 'branch_id' => null, 'price' => 100]);
            $this->fail('Expected 403 for null-branch manager creating a meal.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
        $this->assertSame($beforeCount, Meal::count());
    }

    public function test_meal_category_resource_and_table_enforce_branch_isolation_and_bulk_delete_authorization(): void
    {
        $this->actingAs($this->manager1);
        $visibleIds = MealCategoryResource::getEloquentQuery()->pluck('id')->all();
        $this->assertContains($this->globalCat->id, $visibleIds);
        $this->assertContains($this->branch1Cat->id, $visibleIds);
        $this->assertNotContains($this->branch2Cat->id, $visibleIds);

        $this->assertTrue(MealCategoryResource::canEdit($this->branch1Cat));
        $this->assertFalse(MealCategoryResource::canEdit($this->globalCat));
        $this->assertFalse(MealCategoryResource::canEdit($this->branch2Cat));
        $this->assertFalse(MealCategoryResource::canDelete($this->globalCat));

        $globalForMgr1 = MealCategoryResource::getEloquentQuery()->findOrFail($this->globalCat->id);
        $this->assertSame(1, (int) $globalForMgr1->meals_count);

        $table = Livewire::actingAs($this->manager1)->test(ListMealCategories::class);
        $table->assertCanSeeTableRecords([$this->globalCat, $this->branch1Cat]);
        $table->assertCanNotSeeTableRecords([$this->branch2Cat]);
        $table->assertTableActionHidden('edit', $this->globalCat);
        $table->assertTableActionDisabled('edit', $this->globalCat);

        $unreferencedGlobal = MealCategory::create(['branch_id' => null, 'name' => 'Global Empty', 'slug' => 'global-empty', 'is_active' => true, 'display_order' => 9]);
        $emptyB1 = MealCategory::create(['branch_id' => $this->branch1->id, 'name' => 'B1 Empty', 'slug' => 'b1-empty', 'is_active' => true, 'display_order' => 10]);

        $table->selectTableRecords([$emptyB1->id, $unreferencedGlobal->id, $this->branch2Cat->id])
            ->callAction(TestAction::make(DeleteBulkAction::class)->table()->bulk());

        $this->assertDatabaseMissing('meal_categories', ['id' => $emptyB1->id]);
        $this->assertDatabaseHas('meal_categories', ['id' => $unreferencedGlobal->id, 'name' => 'Global Empty']);
        $this->assertDatabaseHas('meal_categories', ['id' => $this->branch2Cat->id, 'name' => 'B2 Specials']);

        $this->actingAs($this->owner);
        $globalForOwner = MealCategoryResource::getEloquentQuery()->findOrFail($this->globalCat->id);
        $this->assertSame(2, (int) $globalForOwner->meals_count);
        $this->assertTrue(MealCategoryResource::canEdit($this->globalCat));
        $this->assertTrue(MealCategoryResource::canDeleteAny());

        $this->actingAs($this->cashier1);
        $this->assertFalse(MealCategoryResource::canAccess());
        $this->get('/admin/meal-categories')->assertStatus(403);
    }

    public function test_meal_categories_active_cache_and_select_options_are_branch_scoped_across_forms_and_catalogs(): void
    {
        Cache::flush();

        $lwForm = Livewire::actingAs($this->manager1)->test(LivewireMealForm::class);
        $lwCatIds = $lwForm->viewData('categories')->pluck('id')->all();
        $this->assertContains($this->globalCat->id, $lwCatIds);
        $this->assertContains($this->branch1Cat->id, $lwCatIds);
        $this->assertNotContains($this->branch2Cat->id, $lwCatIds);

        $createModal = Livewire::actingAs($this->chef1)->test(CreateMealModal::class)->dispatch('open-create-meal-modal');
        $this->assertNotContains($this->branch2Cat->id, $createModal->viewData('categories')->pluck('id')->all());

        $editModal = Livewire::actingAs($this->chef1)->test(EditMealModal::class)->dispatch('edit-meal', id: $this->branch1Meal->id);
        $this->assertNotContains($this->branch2Cat->id, $editModal->viewData('categories')->pluck('id')->all());

        $catalogTable = Livewire::actingAs($this->chef1)->test(MealCatalogTable::class);
        $this->assertNotContains($this->branch2Cat->id, $catalogTable->viewData('categories')->pluck('id')->all());

        $posCatalog = Livewire::actingAs($this->cashier1)->test(PosMealCatalog::class);
        $this->assertNotContains($this->branch2Cat->id, $posCatalog->viewData('categories')->pluck('id')->all());

        Livewire::actingAs($this->manager1)->test(Dashboard::class)
            ->assertSee('Global Mains')->assertSee('B1 Specials')->assertDontSee('B2 Specials');

        // Tampered foreign category submission is rejected on Livewire CreateMealModal & Filament ListMeals
        $beforeCount = Meal::count();
        Livewire::actingAs($this->chef1)->test(CreateMealModal::class)
            ->dispatch('open-create-meal-modal')
            ->set('name', 'Tampered Cat Dish')
            ->set('category_id', $this->branch2Cat->id)
            ->set('price', '150.00')
            ->call('save')
            ->assertHasErrors(['category_id']);

        Livewire::actingAs($this->manager1)->test(ListMeals::class)
            ->callAction('create', data: [
                'name' => 'Filament Tampered Cat Dish',
                'category_id' => $this->branch2Cat->id,
                'price' => '150.00',
            ])
            ->assertHasActionErrors(['category_id']);
        $this->assertSame($beforeCount, Meal::count());

        // Mutating Branch 2 category invalidates Branch 2 cache without touching Branch 1 cache
        MealCategory::cachedActiveForBranch($this->branch1->id);
        MealCategory::cachedActiveForBranch($this->branch2->id);
        $this->assertTrue(Cache::has("meal_categories_active_branch_{$this->branch1->id}"));
        $this->assertTrue(Cache::has("meal_categories_active_branch_{$this->branch2->id}"));

        $this->branch2Cat->update(['name' => 'B2 Updated Specials']);
        $this->assertTrue(Cache::has("meal_categories_active_branch_{$this->branch1->id}"));
        $this->assertFalse(Cache::has("meal_categories_active_branch_{$this->branch2->id}"));
    }

    public function test_meal_resource_and_meals_table_enforce_create_edit_delete_restore_and_force_delete_authorization(): void
    {
        $this->actingAs($this->manager1);
        $this->assertTrue(MealResource::canAccess());
        $this->assertTrue(MealResource::canCreate());
        $this->assertFalse(MealResource::canEdit($this->globalMeal));
        $this->assertFalse(MealResource::canEdit($this->branch2Meal));
        $this->assertTrue(MealResource::canDelete($this->globalMeal));
        $this->assertFalse(MealResource::canRestore($this->branch1Meal));
        $this->assertFalse(MealResource::canRestoreAny());
        $this->assertFalse(MealResource::canForceDelete($this->branch1Meal));
        $this->assertFalse(MealResource::canForceDeleteAny());

        // Forged branch_id in Filament create form is forced to manager1's own branch
        Livewire::actingAs($this->manager1)->test(ListMeals::class)
            ->callAction('create', data: [
                'name' => 'B1 Created Via Filament',
                'category_id' => $this->branch1Cat->id,
                'branch_id' => $this->branch2->id,
                'price' => '210.00',
                'is_available' => true,
                'is_active' => true,
            ])
            ->assertHasNoActionErrors();

        $created = Meal::where('name', 'B1 Created Via Filament')->firstOrFail();
        $this->assertSame($this->branch1->id, (int) $created->branch_id);

        // Bulk delete preserves global & Branch 2 meals while deleting own branch meal
        $listMeals = Livewire::actingAs($this->manager1)->test(ListMeals::class);
        $listMeals->assertTableActionHidden('edit', $this->globalMeal);
        $listMeals->assertTableActionDisabled('edit', $this->globalMeal);

        $listMeals->selectTableRecords([$created->id, $this->globalMeal->id, $this->branch2Meal->id])
            ->callAction(TestAction::make(DeleteBulkAction::class)->table()->bulk());

        $this->assertNotSoftDeleted('meals', ['id' => $created->id]);
        $this->assertNotSoftDeleted('meals', ['id' => $this->globalMeal->id]);
        $this->assertNotSoftDeleted('meals', ['id' => $this->branch2Meal->id]);

        // Trashed global & Branch 2 meals cannot be restored or force-deleted by Branch 1 manager
        $this->globalMeal->delete();
        $this->branch2Meal->delete();

        $trashedList = Livewire::actingAs($this->manager1)->test(ListMeals::class)
            ->filterTable('trashed', true);
        $trashedList->assertActionHidden(TestAction::make(RestoreBulkAction::class)->table()->bulk());
        $trashedList->assertActionHidden(TestAction::make(ForceDeleteBulkAction::class)->table()->bulk());

        $this->assertTrue(MealResource::getRestoreAuthorizationResponse($this->globalMeal)->denied());
        $this->assertTrue(MealResource::getForceDeleteAuthorizationResponse($this->globalMeal)->denied());
        $this->assertSoftDeleted('meals', ['id' => $this->globalMeal->id]);
        $this->assertNotSoftDeleted('meals', ['id' => $created->id]);

        // Manager without menu.manage (retaining admin.access) is denied
        Role::findByName(StaffRole::BRANCH_MANAGER->value, 'web')->revokePermissionTo('menu.manage');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->manager1->unsetRelation('roles')->unsetRelation('permissions');

        $this->actingAs($this->manager1);
        $this->assertFalse(MealResource::canAccess());
        $this->assertFalse(MealResource::canCreate());
        $this->assertFalse(MealResource::canDeleteAny());
        $this->get('/admin/meals')->assertStatus(403);
    }

    public function test_meal_eloquent_booted_hook_invalidates_branch_and_global_menu_versions_without_cross_branch_leakage(): void
    {
        Cache::forever("branch_{$this->branch1->id}_menu_version", 1000);
        Cache::forever("branch_{$this->branch2->id}_menu_version", 2000);
        Cache::forever('branch_global_menu_version', 3000);

        $b1Meal = Meal::create(['branch_id' => $this->branch1->id, 'category_id' => $this->branch1Cat->id, 'name' => 'Booted B1', 'price' => 110.00]);
        $this->assertGreaterThan(1000, Cache::get("branch_{$this->branch1->id}_menu_version"));
        $this->assertSame(2000, Cache::get("branch_{$this->branch2->id}_menu_version"));
        $this->assertSame(3000, Cache::get('branch_global_menu_version'));

        Cache::forever("branch_{$this->branch1->id}_menu_version", 1000);
        $b1Meal->update(['price' => 125.00]);
        $this->assertGreaterThan(1000, Cache::get("branch_{$this->branch1->id}_menu_version"));
        $this->assertSame(2000, Cache::get("branch_{$this->branch2->id}_menu_version"));
        $this->assertSame(3000, Cache::get('branch_global_menu_version'));

        Cache::forever("branch_{$this->branch1->id}_menu_version", 1000);
        $b1Meal->delete();
        $this->assertGreaterThan(1000, Cache::get("branch_{$this->branch1->id}_menu_version"));
        $this->assertSame(2000, Cache::get("branch_{$this->branch2->id}_menu_version"));

        Cache::forever("branch_{$this->branch1->id}_menu_version", 1000);
        $b1Meal->restore();
        $this->assertGreaterThan(1000, Cache::get("branch_{$this->branch1->id}_menu_version"));
        $this->assertSame(2000, Cache::get("branch_{$this->branch2->id}_menu_version"));

        // Global meal update/delete/restore bumps global + all branches
        Cache::forever("branch_{$this->branch1->id}_menu_version", 1000);
        Cache::forever("branch_{$this->branch2->id}_menu_version", 2000);
        Cache::forever('branch_global_menu_version', 3000);

        $this->globalMeal->update(['price' => 195.00]);
        $this->assertGreaterThan(1000, Cache::get("branch_{$this->branch1->id}_menu_version"));
        $this->assertGreaterThan(2000, Cache::get("branch_{$this->branch2->id}_menu_version"));
        $this->assertGreaterThan(3000, Cache::get('branch_global_menu_version'));
    }

    public function test_update_branch_portions_action_dispatches_meal_availability_toggled_with_correct_branch_id_and_availability(): void
    {
        Event::fake([MealAvailabilityToggled::class]);
        Cache::forever("branch_{$this->branch1->id}_menu_version", 1000);
        Cache::forever("branch_{$this->branch2->id}_menu_version", 2000);
        Cache::forever('branch_global_menu_version', 3000);

        $this->actingAs($this->chef1);
        (new UpdateBranchPortionsAction)->handle($this->globalMeal->id, true, 5);

        Event::assertDispatched(MealAvailabilityToggled::class, function (MealAvailabilityToggled $event) {
            return $event->meal->id === $this->globalMeal->id
                && $event->branchId === $this->branch1->id
                && $event->effectiveAvailability === true
                && $event->broadcastOn()[0]->name === 'private-branch.'.$this->branch1->id
                && $event->broadcastWith()['branch_id'] === $this->branch1->id
                && $event->broadcastWith()['is_available'] === true;
        });

        $this->assertDatabaseHas('meal_branch_availabilities', [
            'meal_id' => $this->globalMeal->id,
            'branch_id' => $this->branch1->id,
            'track_stock' => 1,
            'stock_quantity' => 5,
            'is_available' => 1,
        ]);
        $this->assertSame(0, MealBranchAvailability::where('branch_id', $this->branch2->id)->count());
        $this->assertGreaterThan(1000, Cache::get("branch_{$this->branch1->id}_menu_version"));
        $this->assertSame(2000, Cache::get("branch_{$this->branch2->id}_menu_version"));
        $this->assertSame(3000, Cache::get('branch_global_menu_version'));

        // Cross-branch & unauthorized actors must fail with 403 and zero writes
        $beforeCount = MealBranchAvailability::count();
        foreach ([[$this->chef1, $this->branch2Meal->id], [$this->cashier1, $this->globalMeal->id]] as [$actor, $mealId]) {
            $this->actingAs($actor);
            try {
                (new UpdateBranchPortionsAction)->handle($mealId, true, 2);
                $this->fail('Expected 403 for unauthorized portions update.');
            } catch (HttpException $e) {
                $this->assertSame(403, $e->getStatusCode());
            }
        }
        $this->assertSame($beforeCount, MealBranchAvailability::count());
    }

    public function test_admin_resources_and_sales_records_enforce_granular_permissions_in_can_access(): void
    {
        foreach ([$this->owner, $this->superAdmin, $this->manager1] as $allowed) {
            $this->actingAs($allowed);
            $this->assertTrue(OrderResource::canAccess());
            $this->assertTrue(SalesRecords::canAccess());
            $this->assertTrue(MealResource::canAccess());
            $this->assertTrue(MealCategoryResource::canAccess());
            $this->assertTrue(UserResource::canAccess());
        }

        $mgrRole = Role::findByName(StaffRole::BRANCH_MANAGER->value, 'web');

        $mgrRole->revokePermissionTo('menu.manage');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->manager1->unsetRelation('roles')->unsetRelation('permissions');
        $this->actingAs($this->manager1);
        $this->assertFalse(MealResource::canAccess());
        $this->assertFalse(MealCategoryResource::canAccess());
        $this->assertTrue(UserResource::canAccess());
        $this->get('/admin/meals')->assertStatus(403);
        $this->get('/admin/meal-categories')->assertStatus(403);

        $mgrRole->givePermissionTo('menu.manage');
        $mgrRole->revokePermissionTo('users.manage');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->manager1->unsetRelation('roles')->unsetRelation('permissions');
        $this->actingAs($this->manager1);
        $this->assertFalse(UserResource::canAccess());
        $this->get('/admin/users')->assertStatus(403);

        $mgrRole->revokePermissionTo(['reports.view', 'pos.refund']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->manager1->unsetRelation('roles')->unsetRelation('permissions');
        $this->actingAs($this->manager1);
        $this->assertFalse(SalesRecords::canAccess());
        $this->assertFalse(OrderResource::canAccess());
        $this->get('/admin/sales-records')->assertStatus(403);
        $this->get('/admin/orders')->assertStatus(403);

        foreach ([$this->cashier1, $this->cook1, $this->chef1] as $floor) {
            $this->actingAs($floor);
            $this->assertFalse(OrderResource::canAccess());
            $this->assertFalse(SalesRecords::canAccess());
            $this->assertFalse(MealResource::canAccess());
            $this->assertFalse(MealCategoryResource::canAccess());
            $this->assertFalse(UserResource::canAccess());
        }
    }

    public function test_daily_sales_revenue_chart_rejects_tampered_branch_id_and_foreign_cashier_id(): void
    {
        Order::create([
            'order_number' => 'B01-CHART-001', 'branch_id' => $this->branch1->id, 'cashier_id' => $this->cashier1->id,
            'status' => OrderStatus::COMPLETED->value, 'payment_status' => PaymentStatus::PAID->value,
            'order_type' => 'dine_in', 'subtotal' => 300.00, 'total_amount' => 300.00, 'created_at' => now(),
        ]);
        Order::create([
            'order_number' => 'B02-CHART-001', 'branch_id' => $this->branch2->id, 'cashier_id' => $this->cashier2->id,
            'status' => OrderStatus::COMPLETED->value, 'payment_status' => PaymentStatus::PAID->value,
            'order_type' => 'dine_in', 'subtotal' => 900.00, 'total_amount' => 900.00, 'created_at' => now(),
        ]);

        $mgrChart = Livewire::actingAs($this->manager1)->test(DailySalesRevenueChart::class, [
            'startDate' => now('Asia/Manila')->toDateString(),
            'endDate' => now('Asia/Manila')->toDateString(),
            'branchId' => $this->branch2->id,
            'cashierId' => $this->cashier2->id,
        ]);
        $mgrChart->assertSuccessful()->assertSee('300')->assertDontSee('900');

        $ownerChart = Livewire::actingAs($this->owner)->test(DailySalesRevenueChart::class, [
            'startDate' => now('Asia/Manila')->toDateString(),
            'endDate' => now('Asia/Manila')->toDateString(),
            'branchId' => $this->branch2->id,
            'cashierId' => $this->cashier2->id,
        ]);
        $ownerChart->assertSuccessful()->assertSee('900')->assertDontSee('300');
    }

    public function test_branch_manager_menu_edits_and_local_overrides_never_leak_globally_or_to_other_branches(): void
    {
        Livewire::actingAs($this->manager1)->test(ListMeals::class)
            ->callAction('create', data: [
                'name' => 'Branch 1 Exclusive Pancit',
                'category_id' => $this->branch1Cat->id,
                'price' => '175.00',
                'is_available' => true,
                'is_active' => true,
            ])
            ->assertHasNoActionErrors();

        $b1Pancit = Meal::where('name', 'Branch 1 Exclusive Pancit')->firstOrFail();
        $this->assertSame($this->branch1->id, (int) $b1Pancit->branch_id);

        $posB2 = Livewire::actingAs($this->cashier2)->test(PosMealCatalog::class);
        $this->assertNotContains($b1Pancit->id, $posB2->viewData('meals')->pluck('id')->all());

        // Menu /menu local override of a global dish writes only to meal_branch_availabilities for branch 1
        Cache::forever("branch_{$this->branch1->id}_menu_version", 1000);
        Cache::forever("branch_{$this->branch2->id}_menu_version", 2000);
        Cache::forever('branch_global_menu_version', 3000);

        Livewire::actingAs($this->manager1)->test(MealCatalogTable::class)
            ->call('toggleAvailability', $this->globalMeal->id);

        $this->assertTrue((bool) $this->globalMeal->fresh()->is_available);
        $this->assertDatabaseHas('meal_branch_availabilities', [
            'meal_id' => $this->globalMeal->id,
            'branch_id' => $this->branch1->id,
            'is_available' => 0,
        ]);
        $this->assertGreaterThan(1000, Cache::get("branch_{$this->branch1->id}_menu_version"));
        $this->assertSame(2000, Cache::get("branch_{$this->branch2->id}_menu_version"));
        $this->assertSame(3000, Cache::get('branch_global_menu_version'));
    }
}
