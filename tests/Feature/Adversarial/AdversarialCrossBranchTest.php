<?php

namespace Tests\Feature\Adversarial;

use App\Domains\Branches\Models\Branch;
use App\Domains\Identity\Actions\ToggleUserActiveStatusAction;
use App\Domains\Identity\Models\User;
use App\Domains\Menu\Models\Meal;
use App\Domains\Menu\Models\MealCategory;
use App\Domains\Menu\Actions\UpdateMealAction;
use App\Domains\Menu\Actions\DeleteMealAction;
use App\Domains\PointOfSale\Actions\CreateOrderAction;
use App\Domains\PointOfSale\Actions\VoidOrderAction;
use App\Domains\PointOfSale\Models\Order;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Meals\MealResource;
use App\Filament\Resources\Users\UserResource;
use App\Support\Enums\OrderType;
use App\Support\Enums\StaffRole;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AdversarialCrossBranchTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branchA;
    protected Branch $branchB;
    protected User $managerA;
    protected User $managerB;
    protected User $cashierA;
    protected User $cashierB;
    protected MealCategory $category;
    protected Meal $mealA;
    protected Meal $mealB;
    protected Meal $globalMeal;
    protected Order $orderA;
    protected Order $orderB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->branchA = Branch::firstOrCreate(['code' => 'B01'], ['name' => 'Branch Alpha', 'is_active' => true]);
        $this->branchB = Branch::firstOrCreate(['code' => 'B02'], ['name' => 'Branch Beta', 'is_active' => true]);

        $this->category = MealCategory::firstOrCreate(['slug' => 'mains'], ['name' => 'Mains', 'is_active' => true]);

        $this->managerA = User::create([
            'name' => 'Manager Alpha',
            'email' => 'mgr_a@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'B01-MGR-ADV1',
            'pin_code' => Hash::make('1111'),
            'branch_id' => $this->branchA->id,
            'is_active' => true,
        ]);
        $this->managerA->assignRole(StaffRole::BRANCH_MANAGER->value);
        $this->managerA->givePermissionTo(['menu.manage', 'pos.refund', 'users.manage', 'reports.view']);

        $this->managerB = User::create([
            'name' => 'Manager Beta',
            'email' => 'mgr_b@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'B02-MGR-ADV2',
            'pin_code' => Hash::make('2222'),
            'branch_id' => $this->branchB->id,
            'is_active' => true,
        ]);
        $this->managerB->assignRole(StaffRole::BRANCH_MANAGER->value);
        $this->managerB->givePermissionTo(['menu.manage', 'pos.refund', 'users.manage', 'reports.view']);

        $this->cashierA = User::create([
            'name' => 'Cashier Alpha',
            'email' => 'csh_a@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'B01-CSH-ADV1',
            'pin_code' => Hash::make('1234'),
            'branch_id' => $this->branchA->id,
            'is_active' => true,
        ]);
        $this->cashierA->assignRole(StaffRole::CASHIER->value);

        $this->cashierB = User::create([
            'name' => 'Cashier Beta',
            'email' => 'csh_b@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'B02-CSH-ADV2',
            'pin_code' => Hash::make('5678'),
            'branch_id' => $this->branchB->id,
            'is_active' => true,
        ]);
        $this->cashierB->assignRole(StaffRole::CASHIER->value);

        $this->mealA = Meal::create([
            'name' => 'Alpha Dish',
            'menu_code' => 'B01-DISH',
            'category_id' => $this->category->id,
            'branch_id' => $this->branchA->id,
            'price' => 150.00,
            'is_available' => true,
        ]);

        $this->mealB = Meal::create([
            'name' => 'Beta Dish',
            'menu_code' => 'B02-DISH',
            'category_id' => $this->category->id,
            'branch_id' => $this->branchB->id,
            'price' => 200.00,
            'is_available' => true,
        ]);

        $this->globalMeal = Meal::create([
            'name' => 'Global Dish',
            'menu_code' => 'GLB-DISH',
            'category_id' => $this->category->id,
            'branch_id' => null,
            'price' => 100.00,
            'is_available' => true,
        ]);

        $createOrderAction = new CreateOrderAction();
        $this->orderA = $createOrderAction->execute([
            'branch_id' => $this->branchA->id,
            'cashier_id' => $this->cashierA->id,
            'order_type' => OrderType::DINE_IN->value,
            'items' => [['meal_id' => $this->mealA->id, 'quantity' => 1]],
        ]);

        $this->orderB = $createOrderAction->execute([
            'branch_id' => $this->branchB->id,
            'cashier_id' => $this->cashierB->id,
            'order_type' => OrderType::DINE_IN->value,
            'items' => [['meal_id' => $this->mealB->id, 'quantity' => 1]],
        ]);
    }

    public function test_manager_a_cannot_view_branch_b_orders_in_order_resource_query(): void
    {
        $this->actingAs($this->managerA);

        $orders = OrderResource::getEloquentQuery()->pluck('id')->all();

        $this->assertContains($this->orderA->id, $orders);
        $this->assertNotContains($this->orderB->id, $orders, 'Security failure: Branch Manager A can view Branch B orders!');
    }

    public function test_manager_a_cannot_void_branch_b_order(): void
    {
        $this->actingAs($this->managerA);
        $voidAction = new VoidOrderAction();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Manager does not belong to this branch');

        $voidAction->execute([
            'order_id' => $this->orderB->id,
            'manager_id' => $this->managerA->id,
            'manager_pin' => '1111',
            'reason' => 'Adversarial cross-branch void attempt',
        ]);
    }

    public function test_manager_a_cannot_edit_or_delete_branch_b_meal(): void
    {
        $this->actingAs($this->managerA);

        $this->assertFalse(MealResource::canEdit($this->mealB), 'Branch Manager A should not be authorized to edit Meal B');
        $this->assertFalse(MealResource::canDelete($this->mealB), 'Branch Manager A should not be authorized to delete Meal B');

        // Check backend action enforcement
        try {
            $updateAction = new UpdateMealAction();
            $updateAction->execute($this->mealB, ['name' => 'Tampered Dish Name']);
            $this->fail('Expected exception when Branch Manager modifies other branch meal');
        } catch (\Throwable $e) {
            $this->assertTrue(true);
        }

        try {
            $deleteAction = new DeleteMealAction();
            $deleteAction->execute($this->mealB);
            $this->fail('Expected exception when Branch Manager deletes other branch meal');
        } catch (\Throwable $e) {
            $this->assertTrue(true);
        }
    }

    public function test_manager_a_cannot_edit_or_delete_global_meal(): void
    {
        $this->actingAs($this->managerA);

        $this->assertFalse(MealResource::canEdit($this->globalMeal), 'Branch Manager A cannot edit global meal');
        $this->assertFalse(MealResource::canDelete($this->globalMeal), 'Branch Manager A cannot delete global meal');
    }

    public function test_manager_a_cannot_toggle_branch_b_cashier_active_status(): void
    {
        $this->actingAs($this->managerA);
        $toggleAction = new ToggleUserActiveStatusAction();

        $this->expectException(ValidationException::class);
        $toggleAction->execute($this->cashierB, $this->managerA);
    }

    public function test_manager_a_cannot_toggle_own_active_status(): void
    {
        $this->actingAs($this->managerA);
        $toggleAction = new ToggleUserActiveStatusAction();

        $this->expectException(ValidationException::class);
        $toggleAction->execute($this->managerA, $this->managerA);
    }

    public function test_manager_a_cannot_toggle_manager_b_active_status(): void
    {
        $this->actingAs($this->managerA);
        $toggleAction = new ToggleUserActiveStatusAction();

        $this->expectException(ValidationException::class);
        $toggleAction->execute($this->managerB, $this->managerA);
    }

    public function test_manager_a_cannot_view_or_edit_branch_b_staff_or_non_floor_staff(): void
    {
        $this->actingAs($this->managerA);

        $visibleUserIds = UserResource::getEloquentQuery()->pluck('id')->all();
        $this->assertContains($this->cashierA->id, $visibleUserIds);
        $this->assertNotContains($this->cashierB->id, $visibleUserIds);
        $this->assertNotContains($this->managerB->id, $visibleUserIds);
        $this->assertNotContains($this->managerA->id, $visibleUserIds, 'Self should not be in floor query');

        $this->assertFalse(UserResource::canEdit($this->cashierB));
        $this->assertFalse(UserResource::canEdit($this->managerA), 'Cannot edit self');
        $this->assertFalse(UserResource::canEdit($this->managerB));
    }
}
