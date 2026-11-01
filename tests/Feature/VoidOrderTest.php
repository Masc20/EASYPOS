<?php

namespace Tests\Feature;

use App\Domains\Branches\Models\Branch;
use App\Domains\Identity\Models\User;
use App\Domains\Kitchen\Models\KitchenTicket;
use App\Domains\Menu\Models\Meal;
use App\Domains\Menu\Models\MealCategory;
use App\Domains\PointOfSale\Actions\VoidOrderAction;
use App\Domains\PointOfSale\Events\OrderVoided;
use App\Domains\PointOfSale\Models\Order;
use App\Domains\PointOfSale\Models\OrderItem;
use App\Domains\PointOfSale\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class VoidOrderTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branch1;
    protected Branch $branch2;
    protected User $manager1;
    protected User $manager2;
    protected User $owner;
    protected User $cashier;
    protected Meal $trackableMeal;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles & permissions
        Permission::create(['name' => 'pos.access']);
        Permission::create(['name' => 'pos.refund']);
        Permission::create(['name' => 'admin.access']);

        $roleOwner = Role::create(['name' => 'owner']);
        $roleManager = Role::create(['name' => 'branch-manager']);
        $roleCashier = Role::create(['name' => 'cashier']);

        $roleOwner->givePermissionTo(Permission::all());
        $roleManager->givePermissionTo(['pos.access', 'pos.refund', 'admin.access']);
        $roleCashier->givePermissionTo(['pos.access']);

        // Branches
        $this->branch1 = Branch::create([
            'name' => 'Branch Alpha',
            'code' => 'ALPHA',
            'address' => '123 Main St',
            'is_active' => true,
        ]);

        $this->branch2 = Branch::create([
            'name' => 'Branch Beta',
            'code' => 'BETA',
            'address' => '456 Second St',
            'is_active' => true,
        ]);

        // Users
        $this->owner = User::create([
            'name' => 'System Owner',
            'email' => 'owner@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'OWN-001',
            'pin_code' => Hash::make('9999'),
            'branch_id' => null,
            'is_active' => true,
        ]);
        $this->owner->assignRole('owner');

        $this->manager1 = User::create([
            'name' => 'Manager Alpha',
            'email' => 'mgr1@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'MGR-001',
            'pin_code' => Hash::make('1234'),
            'branch_id' => $this->branch1->id,
            'is_active' => true,
        ]);
        $this->manager1->assignRole('branch-manager');

        $this->manager2 = User::create([
            'name' => 'Manager Beta',
            'email' => 'mgr2@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'MGR-002',
            'pin_code' => Hash::make('5678'),
            'branch_id' => $this->branch2->id,
            'is_active' => true,
        ]);
        $this->manager2->assignRole('branch-manager');

        $this->cashier = User::create([
            'name' => 'Cashier Alpha',
            'email' => 'csh1@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'CSH-001',
            'pin_code' => Hash::make('0000'),
            'branch_id' => $this->branch1->id,
            'is_active' => true,
        ]);
        $this->cashier->assignRole('cashier');

        // Meal with tracked stock
        $category = MealCategory::create(['name' => 'Mains', 'slug' => 'mains', 'is_active' => true]);
        $this->trackableMeal = Meal::create([
            'category_id' => $category->id,
            'name' => 'Burger Deluxe',
            'price' => 150.00,
            'is_available' => true,
            'track_stock' => true,
            'stock_quantity' => 20,
        ]);
    }

    public function test_manager_can_void_order_with_valid_pin_and_restores_stock(): void
    {
        Event::fake([OrderVoided::class]);

        $order = Order::create([
            'order_number' => 'ORD-TEST-101',
            'branch_id' => $this->branch1->id,
            'cashier_id' => $this->cashier->id,
            'subtotal' => 300.00,
            'total_amount' => 300.00,
            'status' => 'pending',
            'payment_status' => 'paid',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'meal_id' => $this->trackableMeal->id,
            'meal_name' => $this->trackableMeal->name,
            'quantity' => 2,
            'unit_price' => 150.00,
            'subtotal' => 300.00,
        ]);

        Payment::create([
            'order_id' => $order->id,
            'branch_id' => $this->branch1->id,
            'cashier_id' => $this->cashier->id,
            'payment_method' => 'cash',
            'amount' => 300.00,
            'status' => 'successful',
        ]);

        KitchenTicket::create([
            'order_id' => $order->id,
            'branch_id' => $this->branch1->id,
            'ticket_number' => 'TICK-101',
            'status' => 'new',
        ]);

        $action = new VoidOrderAction();
        $voidedOrder = $action->execute([
            'order_id' => $order->id,
            'manager_id' => $this->manager1->emp_id,
            'manager_pin' => '1234',
            'reason' => 'Customer walked out before preparation',
            'cashier_id' => $this->cashier->id,
        ]);

        $this->assertEquals('cancelled', $voidedOrder->status);
        $this->assertEquals('refunded', $voidedOrder->payment_status);
        $this->assertStringContainsString('Customer walked out', $voidedOrder->notes);

        // Verify Payment updated
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'status' => 'refunded',
        ]);

        // Verify Kitchen ticket updated
        $this->assertDatabaseHas('kitchen_tickets', [
            'order_id' => $order->id,
            'status' => 'cancelled',
        ]);

        // Verify stock restored (20 + 2 = 22)
        $this->assertEquals(22, $this->trackableMeal->fresh()->stock_quantity);

        // Verify event fired
        Event::assertDispatched(OrderVoided::class, function ($event) use ($order) {
            return $event->order->id === $order->id;
        });
    }

    public function test_owner_can_void_historical_completed_order_from_any_branch_without_pin(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-OLD-999',
            'branch_id' => $this->branch2->id,
            'cashier_id' => $this->cashier->id,
            'subtotal' => 500.00,
            'total_amount' => 500.00,
            'status' => 'completed',
            'payment_status' => 'paid',
            'completed_at' => now()->subWeeks(3),
            'created_at' => now()->subWeeks(3),
        ]);

        $action = new VoidOrderAction();
        $voidedOrder = $action->execute([
            'order_id' => $order->id,
            'manager_id' => $this->owner->id,
            'reason' => 'Audit correction for accounting discrepancy',
            'skip_pin_check' => true,
        ]);

        $this->assertEquals('cancelled', $voidedOrder->status);
        $this->assertEquals('refunded', $voidedOrder->payment_status);
        $this->assertStringContainsString('Audit correction', $voidedOrder->notes);
    }

    public function test_manager_cannot_void_order_with_invalid_pin(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-TEST-102',
            'branch_id' => $this->branch1->id,
            'cashier_id' => $this->cashier->id,
            'subtotal' => 150.00,
            'total_amount' => 150.00,
            'status' => 'pending',
            'payment_status' => 'paid',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid manager PIN.');

        $action = new VoidOrderAction();
        $action->execute([
            'order_id' => $order->id,
            'manager_id' => $this->manager1->emp_id,
            'manager_pin' => '0000', // incorrect PIN
            'reason' => 'Testing wrong PIN',
        ]);
    }

    public function test_manager_from_different_branch_cannot_void_order(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-TEST-103',
            'branch_id' => $this->branch1->id,
            'cashier_id' => $this->cashier->id,
            'subtotal' => 150.00,
            'total_amount' => 150.00,
            'status' => 'pending',
            'payment_status' => 'paid',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Manager does not belong to this branch.');

        $action = new VoidOrderAction();
        // manager2 belongs to branch2, attempting to void branch1 order
        $action->execute([
            'order_id' => $order->id,
            'manager_id' => $this->manager2->emp_id,
            'manager_pin' => '5678',
            'reason' => 'Cross-branch test',
        ]);
    }

    public function test_cashier_without_refund_permission_cannot_void_order(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-TEST-104',
            'branch_id' => $this->branch1->id,
            'cashier_id' => $this->cashier->id,
            'subtotal' => 150.00,
            'total_amount' => 150.00,
            'status' => 'pending',
            'payment_status' => 'paid',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('User is not authorized to approve voids.');

        $action = new VoidOrderAction();
        $action->execute([
            'order_id' => $order->id,
            'manager_id' => $this->cashier->emp_id,
            'manager_pin' => '0000',
            'reason' => 'Cashier self-void attempt',
        ]);
    }
}
