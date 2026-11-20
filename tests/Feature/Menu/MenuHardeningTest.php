<?php

namespace Tests\Feature\Menu;

use App\Domains\Branches\Models\Branch;
use App\Domains\Identity\Models\User;
use App\Domains\Menu\Actions\CreateMealAction;
use App\Domains\Menu\Actions\DeleteMealAction;
use App\Domains\Menu\Actions\ToggleMealAvailabilityAction;
use App\Domains\Menu\Actions\UpdateBranchPortionsAction;
use App\Domains\Menu\Actions\UpdateMealAction;
use App\Domains\Menu\Livewire\CreateMealModal;
use App\Domains\Menu\Livewire\DailySpecialsBoard;
use App\Domains\Menu\Livewire\EditMealModal;
use App\Domains\Menu\Livewire\MealCatalogTable;
use App\Domains\Menu\Livewire\MealForm;
use App\Domains\Menu\Models\Meal;
use App\Domains\Menu\Models\MealBranchAvailability;
use App\Domains\Menu\Models\MealCategory;
use App\Domains\PointOfSale\Livewire\MealCatalog as PosMealCatalog;
use App\Support\Enums\StaffRole;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class MenuHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branch1;

    protected User $chef1;

    protected User $cashier1;

    protected User $nullBranchChef;

    protected MealCategory $category;

    protected Meal $branch1Meal;

    protected Meal $globalMeal;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->branch1 = Branch::firstOrCreate(['code' => 'B01'], ['name' => 'Branch One', 'is_active' => true]);
        $this->chef1 = User::where('emp_id', 'B01-CHF-001')->firstOrFail();
        $this->cashier1 = User::where('emp_id', 'B01-CSH-001')->firstOrFail();

        $this->nullBranchChef = User::create([
            'name' => 'Null Branch Chef',
            'email' => 'null_chef_hardening@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'HQ-CHF-NULL1',
            'branch_id' => null,
            'is_active' => true,
        ]);
        $this->nullBranchChef->assignRole(StaffRole::CHEF->value);

        $this->category = MealCategory::create([
            'name' => 'Mains',
            'slug' => 'mains',
            'is_active' => true,
            'display_order' => 1,
        ]);

        $this->branch1Meal = Meal::create([
            'branch_id' => $this->branch1->id,
            'category_id' => $this->category->id,
            'name' => 'Branch 1 Adobo',
            'menu_code' => 'B1-HRD-01',
            'price' => 200.00,
            'is_available' => true,
            'is_active' => true,
        ]);

        $this->globalMeal = Meal::create([
            'branch_id' => null,
            'category_id' => $this->category->id,
            'name' => 'Global Sinigang',
            'menu_code' => 'GL-HRD-01',
            'price' => 250.00,
            'is_available' => true,
            'is_active' => true,
        ]);
    }

    public function test_same_second_menu_mutations_bump_version_monotonically_and_pos_detects_second_mutation(): void
    {
        $frozenTime = now()->startOfSecond();
        $this->travelTo($frozenTime);
        $ts = $frozenTime->timestamp;

        Cache::forget("branch_{$this->branch1->id}_menu_version");
        Cache::forget('branch_global_menu_version');

        $posCatalog = Livewire::actingAs($this->cashier1)->test(PosMealCatalog::class);

        $this->actingAs($this->chef1);

        // Mutation 1 in same second: CreateMealAction
        $created = (new CreateMealAction)->handle([
            'name' => 'Same Second Dish',
            'category_id' => $this->category->id,
            'branch_id' => $this->branch1->id,
            'price' => 180.00,
        ]);
        $v1 = (int) Cache::get("branch_{$this->branch1->id}_menu_version");
        $this->assertGreaterThanOrEqual($ts, $v1);

        // POS syncs after mutation 1
        $this->actingAs($this->cashier1);
        $posCatalog->call('syncAvailability');
        $this->assertTrue(isset($posCatalog->effects['html']));

        // Mutation 2 in EXACT SAME second: UpdateMealAction must advance monotonically (> v1, never overwrite back to $ts)
        $this->actingAs($this->chef1);
        (new UpdateMealAction)->handle($created, ['price' => 195.00]);
        $v2 = (int) Cache::get("branch_{$this->branch1->id}_menu_version");
        $this->assertGreaterThan($v1, $v2);

        // POS syncs again in the same second -> MUST detect mutation 2
        $this->actingAs($this->cashier1);
        $posCatalog->call('syncAvailability');
        $this->assertTrue(isset($posCatalog->effects['html']), 'POS MealCatalog must detect second mutation within the same second.');

        // Mutation 3 in same second: ToggleMealAvailabilityAction on branch meal
        $this->actingAs($this->chef1);
        (new ToggleMealAvailabilityAction)->handle($created);
        $v3 = (int) Cache::get("branch_{$this->branch1->id}_menu_version");
        $this->assertGreaterThan($v2, $v3);

        // Mutation 4 in same second: ToggleMealAvailabilityAction on global meal (branch override)
        (new ToggleMealAvailabilityAction)->handle($this->globalMeal);
        $v4 = (int) Cache::get("branch_{$this->branch1->id}_menu_version");
        $this->assertGreaterThan($v3, $v4);

        // Mutation 5 in same second: DeleteMealAction
        (new DeleteMealAction)->handle($created);
        $v5 = (int) Cache::get("branch_{$this->branch1->id}_menu_version");
        $this->assertGreaterThan($v4, $v5);
    }

    public function test_meal_catalog_table_clamps_per_page_between_1_and_100(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            Meal::create([
                'branch_id' => $this->branch1->id,
                'category_id' => $this->category->id,
                'name' => "Extra Dish {$i}",
                'menu_code' => "EX-{$i}",
                'price' => 100.00,
                'is_available' => true,
            ]);
        }

        $table = Livewire::actingAs($this->chef1)->test(MealCatalogTable::class);

        $table->set('perPage', 500)
            ->assertSet('perPage', 100);

        $table->set('perPage', 0)
            ->assertSet('perPage', 1);
        $this->assertCount(1, $table->viewData('meals'));

        $table->set('perPage', -10)
            ->assertSet('perPage', 1);
        $this->assertCount(1, $table->viewData('meals'));
    }

    public function test_locked_id_properties_reject_client_tampering(): void
    {
        try {
            Livewire::actingAs($this->chef1)
                ->test(EditMealModal::class)
                ->set('mealId', $this->branch1Meal->id);
            $this->fail('Expected CannotUpdateLockedPropertyException on EditMealModal::$mealId.');
        } catch (CannotUpdateLockedPropertyException) {
            $this->assertTrue(true);
        }

        try {
            Livewire::actingAs($this->chef1)
                ->test(MealForm::class)
                ->set('mealId', $this->branch1Meal->id);
            $this->fail('Expected CannotUpdateLockedPropertyException on MealForm::$mealId.');
        } catch (CannotUpdateLockedPropertyException) {
            $this->assertTrue(true);
        }

        try {
            Livewire::actingAs($this->chef1)
                ->test(MealCatalogTable::class)
                ->set('portionsMealId', $this->branch1Meal->id);
            $this->fail('Expected CannotUpdateLockedPropertyException on MealCatalogTable::$portionsMealId.');
        } catch (CannotUpdateLockedPropertyException) {
            $this->assertTrue(true);
        }
    }

    public function test_menu_actions_and_livewire_components_fail_closed_when_unauthenticated(): void
    {
        auth()->logout();
        $initialCount = Meal::count();

        $actionCalls = [
            fn () => (new CreateMealAction)->handle([
                'name' => 'Guest Meal',
                'category_id' => $this->category->id,
                'price' => 100.00,
            ]),
            fn () => (new UpdateMealAction)->handle($this->branch1Meal, ['price' => 1.00]),
            fn () => (new DeleteMealAction)->handle($this->branch1Meal),
            fn () => (new ToggleMealAvailabilityAction)->handle($this->branch1Meal),
            fn () => (new UpdateBranchPortionsAction)->handle($this->branch1Meal->id, true, 5),
        ];

        foreach ($actionCalls as $call) {
            try {
                $call();
                $this->fail('Expected 403 HttpException for unauthenticated Menu action.');
            } catch (HttpException $e) {
                $this->assertSame(403, $e->getStatusCode());
            }
        }

        Livewire::test(MealCatalogTable::class)->assertForbidden();
        Livewire::test(DailySpecialsBoard::class)->assertForbidden();
        Livewire::test(CreateMealModal::class)->assertForbidden();
        Livewire::test(EditMealModal::class)->assertForbidden();
        Livewire::test(MealForm::class)->assertForbidden();

        $this->assertSame($initialCount, Meal::count());
        $this->assertSame('200.00', $this->branch1Meal->fresh()->price);
        $this->assertTrue((bool) $this->branch1Meal->fresh()->is_available);
        $this->assertNotSoftDeleted('meals', ['id' => $this->branch1Meal->id]);
    }

    public function test_null_branch_scoped_chef_fails_closed_without_writing_branch_zero_records(): void
    {
        $this->actingAs($this->nullBranchChef);
        $initialCount = Meal::count();

        $calls = [
            // Omitting branch_id previously cast (int) null -> 0
            fn () => (new CreateMealAction)->handle([
                'name' => 'Null Branch Leak',
                'category_id' => $this->category->id,
                'price' => 100.00,
            ]),
            fn () => (new UpdateMealAction)->handle($this->branch1Meal, ['price' => 10.00]),
            fn () => (new DeleteMealAction)->handle($this->branch1Meal),
            // Global meal toggle previously cast (int) null -> 0 in meal_branch_availabilities
            fn () => (new ToggleMealAvailabilityAction)->handle($this->globalMeal),
            fn () => (new UpdateBranchPortionsAction)->handle($this->globalMeal->id, true, 4),
        ];

        foreach ($calls as $call) {
            try {
                $call();
                $this->fail('Expected 403 HttpException for null-branch scoped chef.');
            } catch (HttpException $e) {
                $this->assertSame(403, $e->getStatusCode());
            }
        }

        Livewire::actingAs($this->nullBranchChef)->test(MealCatalogTable::class)->assertForbidden();
        Livewire::actingAs($this->nullBranchChef)->test(DailySpecialsBoard::class)->assertForbidden();

        $this->assertSame($initialCount, Meal::count());
        $this->assertSame(0, MealBranchAvailability::count());
    }

    public function test_menu_livewire_components_forbid_cashier_and_cook_and_allow_authorized_menu_roles(): void
    {
        $cook1 = User::where('emp_id', 'B01-COK-001')->firstOrFail();
        $owner = User::where('email', 'owner@easypos.com')->firstOrFail();
        $superAdmin = User::where('email', 'admin@easypos.com')->firstOrFail();
        $manager1 = User::where('email', 'mgr-b01@easypos.com')->firstOrFail();

        $components = [
            MealCatalogTable::class,
            DailySpecialsBoard::class,
            CreateMealModal::class,
            EditMealModal::class,
            MealForm::class,
        ];

        foreach ([$this->cashier1, $cook1] as $deniedUser) {
            foreach ($components as $component) {
                Livewire::actingAs($deniedUser)->test($component)->assertForbidden();
            }
        }

        foreach ([$owner, $manager1, $this->chef1] as $allowedUser) {
            foreach ($components as $component) {
                Livewire::actingAs($allowedUser)->test($component)->assertOk();
            }
        }

        // HQ menu management does not grant the separate floor catalog permission.
        Livewire::actingAs($superAdmin)->test(MealCatalogTable::class)->assertForbidden();
        foreach ([DailySpecialsBoard::class, CreateMealModal::class, EditMealModal::class, MealForm::class] as $component) {
            Livewire::actingAs($superAdmin)->test($component)->assertOk();
        }
    }

    public function test_menu_view_only_user_can_render_menu_components_but_cannot_invoke_menu_manage_actions(): void
    {
        $viewOnly = User::create([
            'name' => 'Menu Viewer Only',
            'email' => 'menu_view_only_hardening@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'B01-VIW-HRD1',
            'branch_id' => $this->branch1->id,
            'is_active' => true,
        ]);
        $viewOnly->givePermissionTo('menu.view');

        foreach ([
            MealCatalogTable::class,
            DailySpecialsBoard::class,
            CreateMealModal::class,
            EditMealModal::class,
            MealForm::class,
        ] as $component) {
            Livewire::actingAs($viewOnly)->test($component)->assertOk();
        }

        Livewire::actingAs($viewOnly)
            ->test(CreateMealModal::class)
            ->call('generateCode')
            ->assertForbidden();

        Livewire::actingAs($viewOnly)
            ->test(CreateMealModal::class)
            ->set('category_id', $this->category->id)
            ->assertForbidden();

        Livewire::actingAs($viewOnly)
            ->test(CreateMealModal::class)
            ->call('openCreateModal')
            ->assertForbidden();

        Livewire::actingAs($viewOnly)
            ->test(EditMealModal::class)
            ->set('category_id', $this->category->id)
            ->assertForbidden();

        Livewire::actingAs($viewOnly)
            ->test(MealForm::class)
            ->set('category_id', $this->category->id)
            ->assertForbidden();

        Livewire::actingAs($viewOnly)
            ->test(MealForm::class)
            ->call('openNewMealModal')
            ->assertForbidden();

        Livewire::actingAs($viewOnly)
            ->test(MealCatalogTable::class)
            ->call('toggleAvailability', $this->branch1Meal->id)
            ->assertForbidden();

        Livewire::actingAs($viewOnly)
            ->test(MealCatalogTable::class)
            ->call('deleteMeal', $this->branch1Meal->id)
            ->assertForbidden();

        Livewire::actingAs($viewOnly)
            ->test(MealCatalogTable::class)
            ->call('editMeal', $this->branch1Meal->id)
            ->assertForbidden();

        Livewire::actingAs($viewOnly)
            ->test(MealCatalogTable::class)
            ->call('openPortionsModal', $this->branch1Meal->id)
            ->assertForbidden();

        $this->assertTrue((bool) $this->branch1Meal->fresh()->is_available);
        $this->assertNotSoftDeleted('meals', ['id' => $this->branch1Meal->id]);
    }

    public function test_meal_actions_enforce_category_branch_ownership(): void
    {
        $branch2 = Branch::firstOrCreate(['code' => 'B02'], ['name' => 'Branch Two', 'is_active' => true]);
        $owner = User::where('email', 'owner@easypos.com')->firstOrFail();

        $branch1Cat = MealCategory::create([
            'branch_id' => $this->branch1->id,
            'name' => 'B1 Exclusives',
            'slug' => 'b1-exclusives',
            'is_active' => true,
            'display_order' => 2,
        ]);

        $branch2Cat = MealCategory::create([
            'branch_id' => $branch2->id,
            'name' => 'B2 Exclusives',
            'slug' => 'b2-exclusives',
            'is_active' => true,
            'display_order' => 3,
        ]);

        $initialCount = Meal::count();

        // Branch 1 chef cannot create or update a meal using a Branch 2 category (403)
        $this->actingAs($this->chef1);

        try {
            (new CreateMealAction)->handle([
                'name' => 'Forged Category Dish',
                'category_id' => $branch2Cat->id,
                'branch_id' => $this->branch1->id,
                'price' => 150.00,
            ]);
            $this->fail('Expected 403 when branch-scoped chef uses another branch category.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }

        $this->assertSame($initialCount, Meal::count());

        try {
            (new UpdateMealAction)->handle($this->branch1Meal, [
                'category_id' => $branch2Cat->id,
            ]);
            $this->fail('Expected 403 when branch-scoped chef moves dish to another branch category.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }

        $this->assertSame($this->category->id, $this->branch1Meal->fresh()->category_id);

        // Branch 1 chef CAN use same-branch and global categories
        $created = (new CreateMealAction)->handle([
            'name' => 'B1 Cat Dish',
            'category_id' => $branch1Cat->id,
            'branch_id' => $this->branch1->id,
            'price' => 175.00,
        ]);
        $this->assertSame($branch1Cat->id, $created->category_id);

        (new UpdateMealAction)->handle($created, [
            'category_id' => $this->category->id,
        ]);
        $this->assertSame($this->category->id, $created->fresh()->category_id);

        // Unscoped owner receives ValidationException on mismatched branch/category combinations
        $this->actingAs($owner);

        try {
            (new CreateMealAction)->handle([
                'name' => 'Global In B1 Cat',
                'category_id' => $branch1Cat->id,
                'branch_id' => null,
                'price' => 200.00,
            ]);
            $this->fail('Expected ValidationException when creating global dish in branch-only category.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('category_id', $e->errors());
        }

        try {
            (new CreateMealAction)->handle([
                'name' => 'B1 In B2 Cat',
                'category_id' => $branch2Cat->id,
                'branch_id' => $this->branch1->id,
                'price' => 200.00,
            ]);
            $this->fail('Expected ValidationException when creating B1 dish in B2 category.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('category_id', $e->errors());
        }

        try {
            (new UpdateMealAction)->handle($this->globalMeal, [
                'category_id' => $branch1Cat->id,
            ]);
            $this->fail('Expected ValidationException when updating global dish to branch-only category.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('category_id', $e->errors());
        }
    }
}
