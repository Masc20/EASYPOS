<?php

namespace Tests\Feature\Security;

use App\Domains\Branches\Models\Branch;
use App\Domains\Identity\Actions\ToggleUserActiveStatusAction;
use App\Domains\Identity\Models\User;
use App\Domains\Menu\Actions\DeleteMealAction;
use App\Domains\Menu\Actions\UpdateMealAction;
use App\Domains\Menu\Models\Meal;
use App\Domains\Menu\Models\MealCategory;
use App\Domains\PointOfSale\Actions\VoidOrderAction;
use App\Domains\PointOfSale\Models\Order;
use App\Domains\PointOfSale\Models\OrderItem;
use App\Domains\PointOfSale\Services\SalesAggregationService;
use App\Support\Enums\OrderStatus;
use App\Support\Enums\PaymentStatus;
use App\Support\Enums\StaffRole;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class Sec01BranchManagerScopingTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branch1;
    protected Branch $branch2;
    protected User $manager1;
    protected User $manager2;
    protected User $owner;
    protected User $cashier1;
    protected User $cashier2;
    protected MealCategory $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->branch1 = Branch::firstOrCreate(['code' => 'B01'], [
            'name' => 'Branch Alpha',
            'is_active' => true,
        ]);

        $this->branch2 = Branch::firstOrCreate(['code' => 'B02'], [
            'name' => 'Branch Beta',
            'is_active' => true,
        ]);

        $this->category = MealCategory::create([
            'name' => 'Entrees',
            'slug' => 'entrees',
            'is_active' => true,
        ]);

        $this->owner = User::where('email', 'owner@easypos.com')->first();
        if (! $this->owner) {
            $this->owner = User::create([
                'name' => 'System Owner',
                'email' => 'owner@easypos.com',
                'password' => Hash::make('password'),
                'emp_id' => 'HQ-ADM-001',
                'pin_code' => Hash::make('9999'),
                'branch_id' => null,
                'is_active' => true,
            ]);
            $this->owner->assignRole(StaffRole::OWNER->value);
        }

        $this->manager1 = User::create([
            'name' => 'Manager Alpha Test',
            'email' => 'mgr1_test@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'B01-MGR-TST1',
            'pin_code' => Hash::make('1234'),
            'branch_id' => $this->branch1->id,
            'is_active' => true,
        ]);
        $this->manager1->assignRole(StaffRole::BRANCH_MANAGER->value);

        $this->manager2 = User::create([
            'name' => 'Manager Beta Test',
            'email' => 'mgr2_test@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'B02-MGR-TST2',
            'pin_code' => Hash::make('5678'),
            'branch_id' => $this->branch2->id,
            'is_active' => true,
        ]);
        $this->manager2->assignRole(StaffRole::BRANCH_MANAGER->value);

        $this->cashier1 = User::create([
            'name' => 'Cashier Alpha Test',
            'email' => 'csh1_test@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'B01-CSH-TST1',
            'pin_code' => Hash::make('1111'),
            'branch_id' => $this->branch1->id,
            'is_active' => true,
        ]);
        $this->cashier1->assignRole(StaffRole::CASHIER->value);

        $this->cashier2 = User::create([
            'name' => 'Cashier Beta Test',
            'email' => 'csh2_test@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'B02-CSH-TST2',
            'pin_code' => Hash::make('2222'),
            'branch_id' => $this->branch2->id,
            'is_active' => true,
        ]);
        $this->cashier2->assignRole(StaffRole::CASHIER->value);
    }

    public function test_branch_manager_is_branch_scoped_and_effective_branch_forces_assigned_branch(): void
    {
        $this->assertTrue($this->manager1->isBranchScoped());
        $this->assertEquals($this->branch1->id, $this->manager1->effectiveBranchId(null));
        // Even if requesting Branch 2, branch-scoped staff is constrained to Branch 1
        $this->assertEquals($this->branch1->id, $this->manager1->effectiveBranchId($this->branch2->id));

        // HQ Owner is not branch-scoped and can select any branch or global null
        $this->assertFalse($this->owner->isBranchScoped());
        $this->assertNull($this->owner->effectiveBranchId(null));
        $this->assertEquals($this->branch2->id, $this->owner->effectiveBranchId($this->branch2->id));
    }

    public function test_branch_manager_cannot_toggle_self_active_status(): void
    {
        $action = new ToggleUserActiveStatusAction();

        $this->expectException(ValidationException::class);
        $action->execute($this->manager1, $this->manager1);

        $this->assertTrue($this->manager1->fresh()->is_active);
    }

    public function test_branch_manager_cannot_modify_or_delete_other_branch_meals(): void
    {
        $mealBranch2 = Meal::create([
            'name' => 'Branch Beta Steak',
            'menu_code' => 'BETA-001',
            'price' => 450.00,
            'branch_id' => $this->branch2->id,
            'category_id' => $this->category->id,
            'is_available' => true,
        ]);

        $this->actingAs($this->manager1);

        // Attempting to update Branch 2 meal must throw 403 HttpException
        try {
            $updateAction = new UpdateMealAction();
            $updateAction->handle($mealBranch2, ['name' => 'Hacked Dish Name', 'price' => 1.00]);
            $this->fail('Expected HttpException (403) was not thrown on cross-branch meal update.');
        } catch (HttpException $e) {
            $this->assertEquals(403, $e->getStatusCode());
        }

        $this->assertEquals('Branch Beta Steak', $mealBranch2->fresh()->name);
        $this->assertEquals(450.00, (float) $mealBranch2->fresh()->price);

        // Attempting to delete Branch 2 meal must throw 403 HttpException
        try {
            $deleteAction = new DeleteMealAction();
            $deleteAction->handle($mealBranch2);
            $this->fail('Expected HttpException (403) was not thrown on cross-branch meal delete.');
        } catch (HttpException $e) {
            $this->assertEquals(403, $e->getStatusCode());
        }

        $this->assertDatabaseHas('meals', ['id' => $mealBranch2->id]);
    }

    public function test_branch_manager_cannot_void_other_branch_orders(): void
    {
        $orderBranch2 = Order::create([
            'order_number' => 'B02-20261110-0001',
            'branch_id' => $this->branch2->id,
            'cashier_id' => $this->cashier2->id,
            'status' => OrderStatus::COMPLETED->value,
            'payment_status' => PaymentStatus::PAID->value,
            'order_type' => 'dine_in',
            'subtotal' => 200.00,
            'total_amount' => 200.00,
        ]);

        $this->actingAs($this->manager1);

        $action = new VoidOrderAction();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Manager does not belong to this branch');

        $action->execute([
            'order_id' => $orderBranch2->id,
            'manager_id' => $this->manager1->id,
            'manager_pin' => '1234',
            'reason' => 'Cross-branch test void attempt',
            'skip_pin_check' => false,
        ]);

        $this->assertEquals(OrderStatus::COMPLETED->value, $orderBranch2->fresh()->status);
    }

    public function test_role_matrix_separates_management_from_floor_staff(): void
    {
        $floorRoles = StaffRole::floorValues();
        $this->assertContains('cashier', $floorRoles);
        $this->assertContains('cook', $floorRoles);
        $this->assertContains('chef', $floorRoles);
        $this->assertNotContains('branch-manager', $floorRoles);
        $this->assertNotContains('super-admin', $floorRoles);
        $this->assertNotContains('owner', $floorRoles);

        $managementRoles = StaffRole::managementValues();
        $this->assertContains('branch-manager', $managementRoles);
        $this->assertContains('super-admin', $managementRoles);
        $this->assertContains('owner', $managementRoles);
    }

    public function test_sales_aggregation_service_isolates_branch_records(): void
    {
        // Branch 1 Order
        $order1 = Order::create([
            'order_number' => 'B01-20261110-0001',
            'branch_id' => $this->branch1->id,
            'cashier_id' => $this->cashier1->id,
            'status' => OrderStatus::COMPLETED->value,
            'payment_status' => PaymentStatus::PAID->value,
            'order_type' => 'dine_in',
            'subtotal' => 300.00,
            'total_amount' => 300.00,
            'created_at' => now(),
        ]);

        // Branch 2 Order
        $order2 = Order::create([
            'order_number' => 'B02-20261110-0002',
            'branch_id' => $this->branch2->id,
            'cashier_id' => $this->cashier2->id,
            'status' => OrderStatus::COMPLETED->value,
            'payment_status' => PaymentStatus::PAID->value,
            'order_type' => 'dine_in',
            'subtotal' => 700.00,
            'total_amount' => 700.00,
            'created_at' => now(),
        ]);

        $service = new SalesAggregationService();

        // Branch 1 aggregation
        $reportB1 = $service->getPeriodicReport('day', now()->toDateString(), now()->toDateString(), $this->branch1->id);
        $this->assertEquals(300.00, (float) $reportB1['totals']['gross_sales']);
        $this->assertEquals(1, $reportB1['totals']['orders_count']);

        // Branch 2 aggregation
        $reportB2 = $service->getPeriodicReport('day', now()->toDateString(), now()->toDateString(), $this->branch2->id);
        $this->assertEquals(700.00, (float) $reportB2['totals']['gross_sales']);
        $this->assertEquals(1, $reportB2['totals']['orders_count']);
    }
}
