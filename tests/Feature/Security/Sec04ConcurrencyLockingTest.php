<?php

namespace Tests\Feature\Security;

use App\Domains\Branches\Models\Branch;
use App\Domains\Identity\Models\User;
use App\Domains\Kitchen\Models\KitchenTicket;
use App\Domains\Menu\Models\Meal;
use App\Domains\Menu\Models\MealCategory;
use App\Domains\PointOfSale\Actions\CreateOrderAction;
use App\Domains\PointOfSale\Actions\ProcessPaymentAction;
use App\Domains\PointOfSale\Actions\VoidOrderAction;
use App\Domains\PointOfSale\Models\Order;
use App\Domains\PointOfSale\Models\Payment;
use App\Support\Enums\OrderStatus;
use App\Support\Enums\OrderType;
use App\Support\Enums\PaymentMethod;
use App\Support\Enums\PaymentStatus;
use App\Support\Enums\StaffRole;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;
use Tests\TestCase;

class Sec04ConcurrencyLockingTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branch;
    protected User $manager;
    protected User $cashier;
    protected MealCategory $category;
    protected Meal $trackableMeal;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->branch = Branch::firstOrCreate(['code' => 'B01'], [
            'name' => 'Concurrency Branch',
            'is_active' => true,
        ]);

        $this->category = MealCategory::firstOrCreate(['slug' => 'burgers'], [
            'name' => 'Burgers',
            'is_active' => true,
        ]);

        $this->manager = User::create([
            'name' => 'Manager Sec04',
            'email' => 'sec04_mgr@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'B01-MGR-S04',
            'pin_code' => Hash::make('4321'),
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);
        $this->manager->assignRole(StaffRole::BRANCH_MANAGER->value);

        $this->cashier = User::create([
            'name' => 'Cashier Sec04',
            'email' => 'sec04_csh@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'B01-CSH-S04',
            'pin_code' => Hash::make('1234'),
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);
        $this->cashier->assignRole(StaffRole::CASHIER->value);

        $this->trackableMeal = Meal::create([
            'name' => 'Angus Beef Burger',
            'menu_code' => 'B01-099',
            'category_id' => $this->category->id,
            'branch_id' => $this->branch->id,
            'price' => 320.00,
            'track_stock' => true,
            'stock_quantity' => 20,
            'is_available' => true,
        ]);
    }

    public function test_payment_idempotency_prevents_duplicate_payments_and_duplicate_kitchen_tickets(): void
    {
        $createAction = new CreateOrderAction();
        $order = $createAction->execute([
            'branch_id' => $this->branch->id,
            'cashier_id' => $this->cashier->id,
            'order_type' => OrderType::DINE_IN->value,
            'items' => [
                ['meal_id' => $this->trackableMeal->id, 'quantity' => 2],
            ],
        ]);

        $paymentAction = new ProcessPaymentAction();

        // 1. Initial legitimate payment
        $paymentAction->execute($order, [
            'cashier_id' => $this->cashier->id,
            'payment_method' => PaymentMethod::CASH->value,
            'amount_tendered' => 640.00,
        ]);

        $this->assertEquals(PaymentStatus::PAID->value, $order->fresh()->payment_status);
        $this->assertEquals(OrderStatus::IN_KITCHEN->value, $order->fresh()->status);
        $this->assertEquals(1, Payment::where('order_id', $order->id)->count());
        $this->assertEquals(1, KitchenTicket::where('order_id', $order->id)->count());

        // 2. Simulated concurrent / retry payment attempt for same order
        try {
            $paymentAction->execute($order->fresh(), [
                'cashier_id' => $this->cashier->id,
                'payment_method' => PaymentMethod::CASH->value,
                'amount_tendered' => 640.00,
            ]);
            $this->fail('Expected InvalidArgumentException for duplicate payment attempt.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('already been paid', $e->getMessage());
        }

        // Assert strictly no duplicate payment or ticket was inserted
        $this->assertEquals(1, Payment::where('order_id', $order->id)->count());
        $this->assertEquals(1, KitchenTicket::where('order_id', $order->id)->count());
    }

    public function test_payment_is_rejected_if_order_has_been_cancelled(): void
    {
        $createAction = new CreateOrderAction();
        $order = $createAction->execute([
            'branch_id' => $this->branch->id,
            'cashier_id' => $this->cashier->id,
            'order_type' => OrderType::DINE_IN->value,
            'items' => [
                ['meal_id' => $this->trackableMeal->id, 'quantity' => 1],
            ],
        ]);

        // Manually mark cancelled
        $order->update(['status' => OrderStatus::CANCELLED->value]);

        $paymentAction = new ProcessPaymentAction();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('has been cancelled');

        $paymentAction->execute($order->fresh(), [
            'cashier_id' => $this->cashier->id,
            'payment_method' => PaymentMethod::CASH->value,
            'amount_tendered' => 320.00,
        ]);

        $this->assertEquals(0, Payment::where('order_id', $order->id)->count());
    }

    public function test_void_idempotency_prevents_duplicate_stock_restoration_under_retries(): void
    {
        // Initial stock: 20
        $this->assertEquals(20, $this->trackableMeal->fresh()->stock_quantity);

        $createAction = new CreateOrderAction();
        // Ordering 3 portions -> stock decreases to 17
        $order = $createAction->execute([
            'branch_id' => $this->branch->id,
            'cashier_id' => $this->cashier->id,
            'order_type' => OrderType::DINE_IN->value,
            'items' => [
                ['meal_id' => $this->trackableMeal->id, 'quantity' => 3],
            ],
        ]);

        $this->assertEquals(17, $this->trackableMeal->fresh()->stock_quantity);

        // 1. Initial valid void approval
        $voidAction = new VoidOrderAction();
        $voidAction->execute([
            'order_id' => $order->id,
            'manager_id' => $this->manager->id,
            'manager_pin' => '4321',
            'reason' => 'Customer changed mind before preparation',
            'skip_pin_check' => false,
        ]);

        // Stock restored from 17 back to 20
        $this->assertEquals(20, $this->trackableMeal->fresh()->stock_quantity);
        $this->assertEquals(OrderStatus::CANCELLED->value, $order->fresh()->status);
        $this->assertEquals(PaymentStatus::REFUNDED->value, $order->fresh()->payment_status);

        // 2. Repeated/concurrent void attempt on same order
        try {
            $voidAction->execute([
                'order_id' => $order->id,
                'manager_id' => $this->manager->id,
                'manager_pin' => '4321',
                'reason' => 'Duplicate void click',
                'skip_pin_check' => false,
            ]);
            $this->fail('Expected InvalidArgumentException when attempting to void already cancelled order.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('already cancelled', $e->getMessage());
        }

        // Critical Financial & Inventory Invariant: Stock must remain at 20, NEVER 23
        $this->assertEquals(20, $this->trackableMeal->fresh()->stock_quantity);
    }
}
