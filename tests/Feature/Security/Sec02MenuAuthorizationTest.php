<?php

namespace Tests\Feature\Security;

use App\Domains\Branches\Models\Branch;
use App\Domains\Identity\Models\User;
use App\Domains\Menu\Actions\CreateMealAction;
use App\Domains\Menu\Actions\DeleteMealAction;
use App\Domains\Menu\Actions\ToggleMealAvailabilityAction;
use App\Domains\Menu\Actions\UpdateMealAction;
use App\Domains\Menu\Models\Meal;
use App\Domains\Menu\Models\MealCategory;
use App\Support\Enums\StaffRole;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class Sec02MenuAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branch1;
    protected Branch $branch2;
    protected User $owner;
    protected User $chef1;
    protected User $cashier1;
    protected MealCategory $category;
    protected Meal $mealBranch1;
    protected Meal $mealBranch2;
    protected Meal $globalMeal;

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

        $this->category = MealCategory::firstOrCreate(['slug' => 'rice-bowls'], [
            'name' => 'Rice Bowls',
            'is_active' => true,
        ]);

        $this->owner = User::firstOrCreate(['email' => 'owner@easypos.com'], [
            'name' => 'System Owner',
            'password' => Hash::make('password'),
            'emp_id' => 'HQ-ADM-001',
            'is_active' => true,
        ]);
        if (! $this->owner->hasRole(StaffRole::OWNER->value)) {
            $this->owner->assignRole(StaffRole::OWNER->value);
        }

        $this->chef1 = User::create([
            'name' => 'Chef Branch One',
            'email' => 'chef1_test@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'B01-CHF-TST1',
            'branch_id' => $this->branch1->id,
            'is_active' => true,
        ]);
        $this->chef1->assignRole(StaffRole::CHEF->value);

        $this->cashier1 = User::create([
            'name' => 'Cashier Branch One',
            'email' => 'cashier1_test@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'B01-CSH-TST1',
            'branch_id' => $this->branch1->id,
            'is_active' => true,
        ]);
        $this->cashier1->assignRole(StaffRole::CASHIER->value);

        $this->mealBranch1 = Meal::create([
            'name' => 'Chicken Adobo Bowl',
            'menu_code' => 'B01-001',
            'category_id' => $this->category->id,
            'branch_id' => $this->branch1->id,
            'price' => 195.00,
            'is_available' => true,
        ]);

        $this->mealBranch2 = Meal::create([
            'name' => 'Pork Belly Sisig Bowl',
            'menu_code' => 'B02-001',
            'category_id' => $this->category->id,
            'branch_id' => $this->branch2->id,
            'price' => 220.00,
            'is_available' => true,
        ]);

        $this->globalMeal = Meal::create([
            'name' => 'Signature Halo-Halo',
            'menu_code' => 'GLB-001',
            'category_id' => $this->category->id,
            'branch_id' => null, // Shared HQ menu item
            'price' => 150.00,
            'is_available' => true,
        ]);
    }

    public function test_user_without_menu_manage_cannot_toggle_meal_availability(): void
    {
        $this->actingAs($this->cashier1);

        $this->expectException(HttpException::class);
        $action = new ToggleMealAvailabilityAction();
        $action->handle($this->mealBranch1);
    }

    public function test_user_cannot_toggle_availability_of_other_branch_meal(): void
    {
        $this->actingAs($this->chef1);

        $this->expectException(HttpException::class);
        $action = new ToggleMealAvailabilityAction();
        $action->handle($this->mealBranch2);
    }

    public function test_branch_staff_cannot_modify_other_branch_meal(): void
    {
        $this->actingAs($this->chef1);

        $action = new UpdateMealAction();

        try {
            $action->handle($this->mealBranch2, ['name' => 'Tampered Dish']);
            $this->fail('Expected 403 HttpException on cross-branch update.');
        } catch (HttpException $e) {
            $this->assertEquals(403, $e->getStatusCode());
        }

        $this->assertEquals('Pork Belly Sisig Bowl', $this->mealBranch2->fresh()->name);
    }

    public function test_branch_staff_cannot_delete_other_branch_meal(): void
    {
        $this->actingAs($this->chef1);

        $action = new DeleteMealAction();

        try {
            $action->handle($this->mealBranch2);
            $this->fail('Expected 403 HttpException on cross-branch delete.');
        } catch (HttpException $e) {
            $this->assertEquals(403, $e->getStatusCode());
        }

        $this->assertDatabaseHas('meals', ['id' => $this->mealBranch2->id]);
    }

    public function test_owner_can_modify_and_toggle_both_branch_and_global_meals(): void
    {
        $this->actingAs($this->owner);

        $toggleAction = new ToggleMealAvailabilityAction();
        $toggled = $toggleAction->handle($this->globalMeal);
        $this->assertFalse($toggled->is_available);

        $updateAction = new UpdateMealAction();
        $updated = $updateAction->handle($this->globalMeal, ['price' => 165.00]);
        $this->assertEquals(165.00, (float) $updated->price);
    }
}
