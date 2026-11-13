<?php

namespace Tests\Feature\Adversarial;

use App\Domains\Branches\Models\Branch;
use App\Domains\Identity\Models\User;
use App\Domains\Menu\Actions\CreateMealAction;
use App\Domains\Menu\Actions\DeleteMealAction;
use App\Domains\Menu\Actions\ToggleMealAvailabilityAction;
use App\Domains\Menu\Actions\UpdateMealAction;
use App\Domains\Menu\Models\Meal;
use App\Domains\Menu\Models\MealCategory;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AdversarialMenuVersionBumpTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branch1;
    protected Branch $branch2;
    protected User $chef1;
    protected User $chef2;
    protected User $admin;
    protected MealCategory $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->branch1 = Branch::where('code', 'B01')->firstOrFail();
        $this->branch2 = Branch::where('code', 'B02')->firstOrFail();

        $this->chef1 = User::factory()->create([
            'branch_id' => $this->branch1->id,
            'name' => 'Chef One',
            'email' => 'chef1@test.com',
        ]);
        $this->chef1->assignRole('chef');

        $this->chef2 = User::factory()->create([
            'branch_id' => $this->branch2->id,
            'name' => 'Chef Two',
            'email' => 'chef2@test.com',
        ]);
        $this->chef2->assignRole('chef');

        $this->admin = User::factory()->create([
            'branch_id' => null,
            'name' => 'System Admin',
            'email' => 'admin@test.com',
        ]);
        $this->admin->assignRole('super-admin');

        $this->category = MealCategory::create([
            'name' => 'Mains',
            'slug' => 'mains',
            'is_active' => true,
            'display_order' => 1,
        ]);
    }

    public function test_branch_a_dish_toggle_bumps_branch_a_and_not_branch_b(): void
    {
        $meal = Meal::create([
            'branch_id' => $this->branch1->id,
            'category_id' => $this->category->id,
            'name' => 'Branch 1 Sisig',
            'price' => 250.00,
            'is_available' => true,
        ]);

        Cache::forever("branch_{$this->branch1->id}_menu_version", 1000);
        Cache::forever("branch_{$this->branch2->id}_menu_version", 2000);
        Cache::forever('branch_global_menu_version', 3000);

        $this->actingAs($this->chef1);
        (new ToggleMealAvailabilityAction())->handle($meal);

        $v1 = Cache::get("branch_{$this->branch1->id}_menu_version");
        $v2 = Cache::get("branch_{$this->branch2->id}_menu_version");
        $vg = Cache::get('branch_global_menu_version');

        $this->assertGreaterThan(1000, $v1, 'Branch 1 menu version must be bumped after toggle');
        $this->assertEquals(2000, $v2, 'Branch 2 menu version must NOT change when Branch 1 dish is toggled');
        $this->assertEquals(3000, $vg, 'Global menu version must NOT change when branch-scoped dish is toggled');
    }

    public function test_branch_a_dish_creation_bumps_branch_a_and_not_branch_b(): void
    {
        Cache::forever("branch_{$this->branch1->id}_menu_version", 1000);
        Cache::forever("branch_{$this->branch2->id}_menu_version", 2000);
        Cache::forever('branch_global_menu_version', 3000);

        $this->actingAs($this->chef1);
        $meal = (new CreateMealAction())->handle([
            'name' => 'New B1 Caldereta',
            'category_id' => $this->category->id,
            'branch_id' => $this->branch1->id,
            'price' => 320.00,
        ]);

        $this->assertNotNull($meal);
        $v1 = Cache::get("branch_{$this->branch1->id}_menu_version");
        $v2 = Cache::get("branch_{$this->branch2->id}_menu_version");
        $vg = Cache::get('branch_global_menu_version');

        $this->assertGreaterThan(1000, $v1, 'Branch 1 menu version must be bumped after dish creation');
        $this->assertEquals(2000, $v2, 'Branch 2 menu version must NOT change when Branch 1 dish is created');
        $this->assertEquals(3000, $vg, 'Global menu version must NOT change when branch-scoped dish is created');
    }

    public function test_branch_a_dish_update_bumps_branch_a_and_not_branch_b(): void
    {
        $meal = Meal::create([
            'branch_id' => $this->branch1->id,
            'category_id' => $this->category->id,
            'name' => 'B1 Kare-Kare',
            'price' => 400.00,
            'is_available' => true,
        ]);

        Cache::forever("branch_{$this->branch1->id}_menu_version", 1000);
        Cache::forever("branch_{$this->branch2->id}_menu_version", 2000);
        Cache::forever('branch_global_menu_version', 3000);

        $this->actingAs($this->chef1);
        (new UpdateMealAction())->handle($meal, ['price' => 450.00]);

        $v1 = Cache::get("branch_{$this->branch1->id}_menu_version");
        $v2 = Cache::get("branch_{$this->branch2->id}_menu_version");
        $vg = Cache::get('branch_global_menu_version');

        $this->assertGreaterThan(1000, $v1, 'Branch 1 menu version must be bumped after dish update');
        $this->assertEquals(2000, $v2, 'Branch 2 menu version must NOT change when Branch 1 dish is updated');
        $this->assertEquals(3000, $vg, 'Global menu version must NOT change when branch-scoped dish is updated');
    }

    public function test_branch_a_dish_deletion_bumps_branch_a_and_not_branch_b(): void
    {
        $meal = Meal::create([
            'branch_id' => $this->branch1->id,
            'category_id' => $this->category->id,
            'name' => 'B1 Lechon Kawali',
            'price' => 280.00,
            'is_available' => true,
        ]);

        Cache::forever("branch_{$this->branch1->id}_menu_version", 1000);
        Cache::forever("branch_{$this->branch2->id}_menu_version", 2000);
        Cache::forever('branch_global_menu_version', 3000);

        $this->actingAs($this->chef1);
        (new DeleteMealAction())->handle($meal);

        $v1 = Cache::get("branch_{$this->branch1->id}_menu_version");
        $v2 = Cache::get("branch_{$this->branch2->id}_menu_version");
        $vg = Cache::get('branch_global_menu_version');

        $this->assertGreaterThan(1000, $v1, 'Branch 1 menu version must be bumped after dish deletion');
        $this->assertEquals(2000, $v2, 'Branch 2 menu version must NOT change when Branch 1 dish is deleted');
        $this->assertEquals(3000, $vg, 'Global menu version must NOT change when branch-scoped dish is deleted');
    }

    public function test_global_dish_toggle_invalidates_all_branches_and_global_version(): void
    {
        $globalMeal = Meal::create([
            'branch_id' => null,
            'category_id' => $this->category->id,
            'name' => 'HQ Master Roast Chicken',
            'price' => 380.00,
            'is_available' => true,
        ]);

        Cache::forever("branch_{$this->branch1->id}_menu_version", 1000);
        Cache::forever("branch_{$this->branch2->id}_menu_version", 2000);
        Cache::forever('branch_global_menu_version', 3000);

        $this->actingAs($this->admin);
        (new ToggleMealAvailabilityAction())->handle($globalMeal);

        $v1 = Cache::get("branch_{$this->branch1->id}_menu_version");
        $v2 = Cache::get("branch_{$this->branch2->id}_menu_version");
        $vg = Cache::get('branch_global_menu_version');

        $this->assertGreaterThan(1000, $v1, 'Branch 1 menu version must be invalidated on global dish toggle');
        $this->assertGreaterThan(2000, $v2, 'Branch 2 menu version must be invalidated on global dish toggle');
        $this->assertGreaterThan(3000, $vg, 'Global menu version must be invalidated on global dish toggle');
    }

    public function test_global_dish_creation_invalidates_all_branches_and_global_version(): void
    {
        Cache::forever("branch_{$this->branch1->id}_menu_version", 1000);
        Cache::forever("branch_{$this->branch2->id}_menu_version", 2000);
        Cache::forever('branch_global_menu_version', 3000);

        $this->actingAs($this->admin);
        $globalMeal = (new CreateMealAction())->handle([
            'name' => 'HQ Signature Halo-Halo',
            'category_id' => $this->category->id,
            'branch_id' => null,
            'price' => 150.00,
        ]);

        $this->assertNotNull($globalMeal);
        $v1 = Cache::get("branch_{$this->branch1->id}_menu_version");
        $v2 = Cache::get("branch_{$this->branch2->id}_menu_version");
        $vg = Cache::get('branch_global_menu_version');

        $this->assertGreaterThan(1000, $v1, 'Branch 1 version must be invalidated on global dish creation');
        $this->assertGreaterThan(2000, $v2, 'Branch 2 version must be invalidated on global dish creation');
        $this->assertGreaterThan(3000, $vg, 'Global version must be invalidated on global dish creation');
    }

    public function test_global_dish_update_invalidates_all_branches_and_global_version(): void
    {
        $globalMeal = Meal::create([
            'branch_id' => null,
            'category_id' => $this->category->id,
            'name' => 'HQ Garlic Rice Platter',
            'price' => 80.00,
            'is_available' => true,
        ]);

        Cache::forever("branch_{$this->branch1->id}_menu_version", 1000);
        Cache::forever("branch_{$this->branch2->id}_menu_version", 2000);
        Cache::forever('branch_global_menu_version', 3000);

        $this->actingAs($this->admin);
        (new UpdateMealAction())->handle($globalMeal, ['price' => 95.00]);

        $v1 = Cache::get("branch_{$this->branch1->id}_menu_version");
        $v2 = Cache::get("branch_{$this->branch2->id}_menu_version");
        $vg = Cache::get('branch_global_menu_version');

        $this->assertGreaterThan(1000, $v1, 'Branch 1 version must be invalidated on global dish update');
        $this->assertGreaterThan(2000, $v2, 'Branch 2 version must be invalidated on global dish update');
        $this->assertGreaterThan(3000, $vg, 'Global version must be invalidated on global dish update');
    }

    public function test_global_dish_deletion_invalidates_all_branches_and_global_version(): void
    {
        $globalMeal = Meal::create([
            'branch_id' => null,
            'category_id' => $this->category->id,
            'name' => 'HQ Seasonal Soup',
            'price' => 120.00,
            'is_available' => true,
        ]);

        Cache::forever("branch_{$this->branch1->id}_menu_version", 1000);
        Cache::forever("branch_{$this->branch2->id}_menu_version", 2000);
        Cache::forever('branch_global_menu_version', 3000);

        $this->actingAs($this->admin);
        (new DeleteMealAction())->handle($globalMeal);

        $v1 = Cache::get("branch_{$this->branch1->id}_menu_version");
        $v2 = Cache::get("branch_{$this->branch2->id}_menu_version");
        $vg = Cache::get('branch_global_menu_version');

        $this->assertGreaterThan(1000, $v1, 'Branch 1 version must be invalidated on global dish deletion');
        $this->assertGreaterThan(2000, $v2, 'Branch 2 version must be invalidated on global dish deletion');
        $this->assertGreaterThan(3000, $vg, 'Global version must be invalidated on global dish deletion');
    }

    public function test_cross_branch_menu_modifications_are_strictly_rejected(): void
    {
        $mealB2 = Meal::create([
            'branch_id' => $this->branch2->id,
            'category_id' => $this->category->id,
            'name' => 'Branch 2 Secret Ribs',
            'price' => 600.00,
            'is_available' => true,
        ]);

        $this->actingAs($this->chef1);

        // 1. Chef 1 cannot toggle Branch 2 dish
        try {
            (new ToggleMealAvailabilityAction())->handle($mealB2);
            $this->fail('Expected 403 HttpException when toggling another branch dish.');
        } catch (HttpException $e) {
            $this->assertEquals(403, $e->getStatusCode());
        }

        // 2. Chef 1 cannot update Branch 2 dish
        try {
            (new UpdateMealAction())->handle($mealB2, ['price' => 50.00]);
            $this->fail('Expected 403 HttpException when updating another branch dish.');
        } catch (HttpException $e) {
            $this->assertEquals(403, $e->getStatusCode());
        }

        // 3. Chef 1 cannot delete Branch 2 dish
        try {
            (new DeleteMealAction())->handle($mealB2);
            $this->fail('Expected 403 HttpException when deleting another branch dish.');
        } catch (HttpException $e) {
            $this->assertEquals(403, $e->getStatusCode());
        }

        // 4. Chef 1 cannot create a dish for Branch 2
        try {
            (new CreateMealAction())->handle([
                'name' => 'Intruder Dish',
                'category_id' => $this->category->id,
                'branch_id' => $this->branch2->id,
                'price' => 100.00,
            ]);
            $this->fail('Expected 403 HttpException when creating dish for another branch.');
        } catch (HttpException $e) {
            $this->assertEquals(403, $e->getStatusCode());
        }
    }
}
