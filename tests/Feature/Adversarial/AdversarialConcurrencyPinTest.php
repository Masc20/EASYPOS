<?php

namespace Tests\Feature\Adversarial;

use App\Domains\Branches\Models\Branch;
use App\Domains\Identity\Models\User;
use App\Domains\Menu\Models\Meal;
use App\Domains\Menu\Models\MealCategory;
use App\Domains\PointOfSale\Actions\CreateOrderAction;
use App\Domains\PointOfSale\Actions\ProcessPaymentAction;
use App\Domains\PointOfSale\Actions\VoidOrderAction;
use App\Domains\PointOfSale\Livewire\Checkout\ManagerPinModal;
use App\Domains\PointOfSale\Models\Order;
use App\Domains\PointOfSale\Models\Payment;
use App\Support\Enums\OrderType;
use App\Support\Enums\PaymentMethod;
use App\Support\Enums\PaymentStatus;
use App\Support\Enums\StaffRole;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use InvalidArgumentException;
use Tests\TestCase;

class AdversarialConcurrencyPinTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branch;
    protected User $cashier;
    protected User $manager;
    protected MealCategory $category;
    protected Meal $trackableMeal;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->branch = Branch::firstOrCreate(['code' => 'B01'], ['name' => 'Flagship Manila', 'is_active' => true]);
        $this->category = MealCategory::firstOrCreate(['slug' => 'mains'], ['name' => 'Mains', 'is_active' => true]);

        $this->cashier = User::create([
            'name' => 'Concurrency Cashier',
            'email' => 'concur_csh@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'B01-CSH-CONC',
            'pin_code' => Hash::make('1234'),
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);
        $this->cashier->assignRole(StaffRole::CASHIER->value);

        $this->manager = User::create([
            'name' => 'Concurrency Manager',
            'email' => 'concur_mgr@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'B01-MGR-CONC',
            'pin_code' => Hash::make('9999'),
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);
        $this->manager->assignRole(StaffRole::BRANCH_MANAGER->value);
        $this->manager->givePermissionTo(['pos.refund', 'pos.access']);

        $this->trackableMeal = Meal::create([
            'name' => 'Tracked Angus Steak',
            'menu_code' => 'B01-STEAK',
            'category_id' => $this->category->id,
            'branch_id' => $this->branch->id,
            'price' => 500.00,
            'is_available' => true,
            'stock_quantity' => 10,
            'track_stock' => true,
        ]);
    }

    public function test_concurrent_payments_prevent_duplicate_payments_and_tickets(): void
    {
        $this->actingAs($this->cashier);
        $createAction = new CreateOrderAction();
        $order = $createAction->execute([
            'branch_id' => $this->branch->id,
            'cashier_id' => $this->cashier->id,
            'order_type' => OrderType::DINE_IN->value,
            'items' => [['meal_id' => $this->trackableMeal->id, 'quantity' => 1]],
        ]);

        $paymentAction = new ProcessPaymentAction();

        // 1st payment succeeds
        $result = $paymentAction->execute($order, [
            'cashier_id' => $this->cashier->id,
            'payment_method' => PaymentMethod::CASH->value,
            'amount_tendered' => 500.00,
        ]);
        $this->assertNotNull($result['payment']);

        // 2nd concurrent payment attempt on same order must be rejected
        try {
            $paymentAction->execute($order, [
                'cashier_id' => $this->cashier->id,
                'payment_method' => PaymentMethod::CASH->value,
                'amount_tendered' => 500.00,
            ]);
            $this->fail('Expected second concurrent payment attempt to be rejected.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('already been paid', $e->getMessage());
        }

        // Assert exactly 1 payment record exists for this order
        $paymentCount = Payment::where('order_id', $order->id)->count();
        $this->assertEquals(1, $paymentCount);

        // Assert exactly 1 kitchen ticket exists for this order
        $ticketCount = \App\Domains\Kitchen\Models\KitchenTicket::where('order_id', $order->id)->count();
        $this->assertEquals(1, $ticketCount);
    }

    public function test_concurrent_voids_prevent_double_stock_restoration(): void
    {
        $this->actingAs($this->cashier);
        $createAction = new CreateOrderAction();

        // Initial stock is 10. Order takes 2 portions -> stock becomes 8.
        $order = $createAction->execute([
            'branch_id' => $this->branch->id,
            'cashier_id' => $this->cashier->id,
            'order_type' => OrderType::DINE_IN->value,
            'items' => [['meal_id' => $this->trackableMeal->id, 'quantity' => 2]],
        ]);

        $this->assertEquals(8, $this->trackableMeal->fresh()->stock_quantity);

        $voidAction = new VoidOrderAction();

        // 1st void succeeds -> stock restored to 10
        $voidedOrder = $voidAction->execute([
            'order_id' => $order->id,
            'manager_id' => $this->manager->emp_id,
            'manager_pin' => '9999',
            'reason' => 'Customer changed mind',
        ]);

        $this->assertEquals(PaymentStatus::REFUNDED->value, $voidedOrder->payment_status);
        $this->assertEquals(10, $this->trackableMeal->fresh()->stock_quantity);

        // 2nd concurrent void attempt on the same order must be rejected and must NOT re-increment stock to 12
        try {
            $voidAction->execute([
                'order_id' => $order->id,
                'manager_id' => $this->manager->emp_id,
                'manager_pin' => '9999',
                'reason' => 'Duplicate void race',
            ]);
            $this->fail('Expected second void attempt on already cancelled order to throw exception.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('already cancelled', $e->getMessage());
        }

        // Stock must strictly remain 10, preventing double inventory restoration
        $this->assertEquals(10, $this->trackableMeal->fresh()->stock_quantity);
    }

    public function test_manager_pin_modal_throttles_after_repeated_invalid_pins(): void
    {
        $this->actingAs($this->cashier);
        $createAction = new CreateOrderAction();
        $order = $createAction->execute([
            'branch_id' => $this->branch->id,
            'cashier_id' => $this->cashier->id,
            'order_type' => OrderType::DINE_IN->value,
            'items' => [['meal_id' => $this->trackableMeal->id, 'quantity' => 1]],
        ]);

        RateLimiter::clear('void-pin-approval:127.0.0.1:' . $this->manager->emp_id . ':' . $order->id);

        $component = new ManagerPinModal();
        $component->openModal($order->id);

        // Fail 5 times with incorrect PIN
        for ($i = 1; $i <= 5; $i++) {
            $success = $component->submitVoidRequest([
                'manager_id' => $this->manager->emp_id,
                'manager_pin' => '0000', // Invalid PIN
                'reason' => 'Tamper test ' . $i,
            ]);
            $this->assertFalse($success);
            $this->assertStringContainsString('Invalid manager PIN', (string) $component->errorMessage);
        }

        // 6th attempt should be blocked by rate limiter lockout
        $sixthAttempt = $component->submitVoidRequest([
            'manager_id' => $this->manager->emp_id,
            'manager_pin' => '9999', // Correct PIN! But terminal must be locked
            'reason' => 'Attempt after lockout',
        ]);

        $this->assertFalse($sixthAttempt);
        $this->assertStringContainsString('Too many failed approval attempts. Terminal locked', (string) $component->errorMessage);
    }

    public function test_floor_session_cannot_bypass_manager_pin(): void
    {
        $this->actingAs($this->cashier);
        session(['auth_method' => 'pin']); // Floor terminal session

        $createAction = new CreateOrderAction();
        $order = $createAction->execute([
            'branch_id' => $this->branch->id,
            'cashier_id' => $this->cashier->id,
            'order_type' => OrderType::DINE_IN->value,
            'items' => [['meal_id' => $this->trackableMeal->id, 'quantity' => 1]],
        ]);

        $voidAction = new VoidOrderAction();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Floor terminal sessions cannot bypass manager PIN verification');

        $voidAction->execute([
            'order_id' => $order->id,
            'manager_id' => $this->manager->id,
            'skip_pin_check' => true, // Attempted bypass!
            'reason' => 'Illegal bypass attempt',
        ]);
    }
}
