<?php

namespace Tests\Feature\Adversarial;

use App\Domains\Branches\Models\Branch;
use App\Domains\Identity\Models\User;
use App\Domains\Menu\Models\Meal;
use App\Domains\Menu\Models\MealCategory;
use App\Domains\PointOfSale\Actions\CreateOrderAction;
use App\Domains\PointOfSale\Actions\ProcessPaymentAction;
use App\Support\Enums\OrderType;
use App\Support\Enums\PaymentMethod;
use App\Support\Enums\StaffRole;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;
use Tests\TestCase;

class AdversarialFinancialTamperingTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branch;
    protected User $cashier;
    protected MealCategory $category;
    protected Meal $meal;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->branch = Branch::firstOrCreate(['code' => 'B01'], [
            'name' => 'Flagship Manila',
            'is_active' => true,
        ]);

        $this->category = MealCategory::firstOrCreate(['slug' => 'mains'], [
            'name' => 'Main Dishes',
            'is_active' => true,
        ]);

        $this->cashier = User::create([
            'name' => 'Adversarial Cashier',
            'email' => 'adv_csh@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'B01-CSH-ADVFIN',
            'pin_code' => Hash::make('1234'),
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);
        $this->cashier->assignRole(StaffRole::CASHIER->value);

        $this->meal = Meal::create([
            'name' => 'Premium Wagyu Bowl',
            'menu_code' => 'B01-WAGYU',
            'category_id' => $this->category->id,
            'branch_id' => $this->branch->id,
            'price' => 1000.00,
            'is_available' => true,
            'stock_quantity' => 10,
            'track_stock' => true,
        ]);
    }

    public function test_client_cannot_forge_prices(): void
    {
        $this->actingAs($this->cashier);
        $action = new CreateOrderAction();

        $order = $action->execute([
            'branch_id' => $this->branch->id,
            'cashier_id' => $this->cashier->id,
            'order_type' => OrderType::DINE_IN->value,
            'items' => [
                [
                    'meal_id' => $this->meal->id,
                    'quantity' => 1,
                    'unit_price' => 1.00, // Attacker forged price
                    'subtotal' => 1.00,
                ],
            ],
            'subtotal' => 1.00,
            'total_amount' => 1.00,
        ]);

        $this->assertEquals(1000.00, (float) $order->fresh()->subtotal);
        $this->assertEquals(1000.00, (float) $order->fresh()->total_amount);
    }

    public function test_client_cannot_submit_negative_or_zero_quantity(): void
    {
        $this->actingAs($this->cashier);
        $action = new CreateOrderAction();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('quantity must be a positive integer');

        $action->execute([
            'branch_id' => $this->branch->id,
            'cashier_id' => $this->cashier->id,
            'order_type' => OrderType::DINE_IN->value,
            'items' => [
                ['meal_id' => $this->meal->id, 'quantity' => -2],
            ],
        ]);
    }

    public function test_client_cannot_use_invalid_payment_method(): void
    {
        $this->actingAs($this->cashier);
        $createAction = new CreateOrderAction();
        $order = $createAction->execute([
            'branch_id' => $this->branch->id,
            'cashier_id' => $this->cashier->id,
            'order_type' => OrderType::DINE_IN->value,
            'items' => [
                ['meal_id' => $this->meal->id, 'quantity' => 1],
            ],
        ]);

        $paymentAction = new ProcessPaymentAction();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid payment method: monopoly_cash');

        $paymentAction->execute($order, [
            'cashier_id' => $this->cashier->id,
            'payment_method' => 'monopoly_cash',
            'amount_tendered' => 1000.00,
        ]);
    }

    public function test_cashier_cannot_apply_custom_discount_without_approval(): void
    {
        $this->actingAs($this->cashier);
        $action = new CreateOrderAction();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('User is not authorized to apply custom discounts');

        $action->execute([
            'branch_id' => $this->branch->id,
            'cashier_id' => $this->cashier->id,
            'order_type' => OrderType::DINE_IN->value,
            'discount_type' => 'custom',
            'custom_discount' => 990.00,
            'items' => [
                ['meal_id' => $this->meal->id, 'quantity' => 1],
            ],
        ]);
    }

    /**
     * EMPIRICAL CHALLENGE:
     * Does CreateOrderAction allow arbitrary 99% discount when discount_type is omitted or set to 'none'?
     */
    public function test_cashier_can_or_cannot_bypass_discount_check_via_none_type(): void
    {
        $this->actingAs($this->cashier);
        $action = new CreateOrderAction();

        $order = $action->execute([
            'branch_id' => $this->branch->id,
            'cashier_id' => $this->cashier->id,
            'order_type' => OrderType::DINE_IN->value,
            'discount_type' => 'none',
            'discount_amount' => 990.00,
            'items' => [
                ['meal_id' => $this->meal->id, 'quantity' => 1],
            ],
        ]);

        // An order with discount_type='none' MUST NOT receive a ₱990.00 discount!
        $this->assertEquals(0.00, (float) $order->fresh()->discount_amount, 'VULNERABILITY CONFIRMED: Cashier bypassed authorization and applied ₱990 discount via discount_type=none!');
    }

    public function test_client_cannot_submit_unknown_discount_type(): void
    {
        $this->actingAs($this->cashier);
        $action = new CreateOrderAction();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid discount type: arbitrary_tampered_discount');

        $action->execute([
            'branch_id' => $this->branch->id,
            'cashier_id' => $this->cashier->id,
            'order_type' => OrderType::DINE_IN->value,
            'discount_type' => 'arbitrary_tampered_discount',
            'items' => [
                ['meal_id' => $this->meal->id, 'quantity' => 1],
            ],
        ]);
    }
}

