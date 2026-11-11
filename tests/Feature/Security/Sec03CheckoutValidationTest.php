<?php

namespace Tests\Feature\Security;

use App\Domains\Branches\Models\Branch;
use App\Domains\Identity\Models\User;
use App\Domains\Menu\Models\Meal;
use App\Domains\Menu\Models\MealCategory;
use App\Domains\PointOfSale\Actions\CreateOrderAction;
use App\Domains\PointOfSale\Actions\ProcessPaymentAction;
use App\Domains\PointOfSale\Livewire\Checkout\CheckoutModal;
use App\Domains\PointOfSale\Models\Order;
use App\Support\Enums\OrderType;
use App\Support\Enums\PaymentMethod;
use App\Support\Enums\StaffRole;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class Sec03CheckoutValidationTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branch;
    protected User $cashier;
    protected User $manager;
    protected MealCategory $category;
    protected Meal $meal1;
    protected Meal $meal2;

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
            'name' => 'Cashier Sec03',
            'email' => 'sec03_csh@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'B01-CSH-S03',
            'pin_code' => Hash::make('1234'),
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);
        $this->cashier->assignRole(StaffRole::CASHIER->value);

        $this->manager = User::create([
            'name' => 'Manager Sec03',
            'email' => 'sec03_mgr@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'B01-MGR-S03',
            'pin_code' => Hash::make('4321'),
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);
        $this->manager->assignRole(StaffRole::BRANCH_MANAGER->value);
        Permission::firstOrCreate(['name' => 'pos.discount', 'guard_name' => 'web']);
        $this->manager->givePermissionTo('pos.discount');

        $this->meal1 = Meal::create([
            'name' => 'Signature Crispy Pata',
            'menu_code' => 'B01-010',
            'category_id' => $this->category->id,
            'branch_id' => $this->branch->id,
            'price' => 750.00,
            'is_available' => true,
        ]);

        $this->meal2 = Meal::create([
            'name' => 'Garlic Fried Rice',
            'menu_code' => 'B01-011',
            'category_id' => $this->category->id,
            'branch_id' => $this->branch->id,
            'price' => 60.00,
            'is_available' => true,
        ]);
    }

    public function test_checkout_recomputes_subtotal_and_total_from_database_prices_ignoring_client_tampering(): void
    {
        $action = new CreateOrderAction();

        // Client attempts to claim unit price is 10.00 instead of DB price 750.00
        $order = $action->execute([
            'branch_id' => $this->branch->id,
            'cashier_id' => $this->cashier->id,
            'order_type' => OrderType::DINE_IN->value,
            'table_number' => 'T-05',
            'customer_name' => 'Valued Diner',
            'items' => [
                [
                    'meal_id' => $this->meal1->id,
                    'quantity' => 2,
                    'unit_price' => 10.00, // Tampered client payload
                    'subtotal' => 20.00,   // Tampered client payload
                ],
                [
                    'meal_id' => $this->meal2->id,
                    'quantity' => 3,
                    'unit_price' => 5.00,  // Tampered client payload
                    'subtotal' => 15.00,   // Tampered client payload
                ],
            ],
            'subtotal' => 35.00,      // Tampered client subtotal
            'total_amount' => 35.00,  // Tampered client total
        ]);

        // Expected: (750 * 2) + (60 * 3) = 1500 + 180 = 1680.00
        $this->assertEquals(1680.00, (float) $order->fresh()->subtotal);
        $this->assertEquals(1680.00, (float) $order->fresh()->total_amount);
        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'meal_id' => $this->meal1->id,
            'unit_price' => 750.00,
            'subtotal' => 1500.00,
        ]);
    }

    public function test_checkout_rejects_unauthorized_custom_discount(): void
    {
        $this->actingAs($this->cashier); // Cashier does not have pos.discount

        $action = new CreateOrderAction();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('User is not authorized to apply custom discounts');

        $action->execute([
            'branch_id' => $this->branch->id,
            'cashier_id' => $this->cashier->id,
            'order_type' => OrderType::DINE_IN->value,
            'discount_type' => 'custom',
            'custom_discount' => 50.00,
            'items' => [
                ['meal_id' => $this->meal2->id, 'quantity' => 2],
            ],
        ]);
    }

    public function test_checkout_rejects_excessive_custom_discount_when_authorized(): void
    {
        $this->actingAs($this->manager); // Manager has pos.discount

        $action = new CreateOrderAction();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Custom discount must be between ₱0.00 and ₱');

        $action->execute([
            'branch_id' => $this->branch->id,
            'cashier_id' => $this->manager->id,
            'order_type' => OrderType::DINE_IN->value,
            'discount_type' => 'custom',
            'custom_discount' => 100.00, // Subtotal is 60.00, 100.00 is excessive!
            'items' => [
                ['meal_id' => $this->meal2->id, 'quantity' => 1],
            ],
        ]);
    }

    public function test_checkout_recomputes_statutory_senior_pwd_discount_server_side(): void
    {
        $action = new CreateOrderAction();

        // Subtotal = 750.00. Statutory 20% Senior/PWD discount = 150.00
        // Client attempts to claim a 500.00 discount
        $order = $action->execute([
            'branch_id' => $this->branch->id,
            'cashier_id' => $this->cashier->id,
            'order_type' => OrderType::DINE_IN->value,
            'discount_type' => 'senior_pwd',
            'discount_amount' => 500.00, // Tampered client claim
            'items' => [
                ['meal_id' => $this->meal1->id, 'quantity' => 1], // 750.00
            ],
        ]);

        // Recomputed server-side: 750 * 0.20 = 150.00
        $this->assertEquals(750.00, (float) $order->fresh()->subtotal);
        $this->assertEquals(150.00, (float) $order->fresh()->discount_amount);
        $this->assertEquals(600.00, (float) $order->fresh()->total_amount);
    }

    public function test_checkout_validates_order_type_against_enum(): void
    {
        $action = new CreateOrderAction();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid order type: delivery');

        $action->execute([
            'branch_id' => $this->branch->id,
            'cashier_id' => $this->cashier->id,
            'order_type' => 'delivery', // Not a valid OrderType in EASYPOS
            'items' => [
                ['meal_id' => $this->meal2->id, 'quantity' => 1],
            ],
        ]);
    }

    public function test_checkout_rejects_invalid_or_negative_or_oversized_quantities(): void
    {
        $action = new CreateOrderAction();

        // 1. Negative quantity
        try {
            $action->execute([
                'branch_id' => $this->branch->id,
                'cashier_id' => $this->cashier->id,
                'order_type' => OrderType::DINE_IN->value,
                'items' => [
                    ['meal_id' => $this->meal2->id, 'quantity' => -5],
                ],
            ]);
            $this->fail('Expected InvalidArgumentException on negative quantity');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('quantity must be a positive integer', $e->getMessage());
        }

        // 2. Oversized quantity > 1000
        try {
            $action->execute([
                'branch_id' => $this->branch->id,
                'cashier_id' => $this->cashier->id,
                'order_type' => OrderType::DINE_IN->value,
                'items' => [
                    ['meal_id' => $this->meal2->id, 'quantity' => 1001],
                ],
            ]);
            $this->fail('Expected InvalidArgumentException on quantity > 1000');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('quantity exceeds maximum allowed limit (1000)', $e->getMessage());
        }
    }

    public function test_checkout_rejects_oversized_strings(): void
    {
        $action = new CreateOrderAction();

        // Table number > 50 chars
        try {
            $action->execute([
                'branch_id' => $this->branch->id,
                'cashier_id' => $this->cashier->id,
                'order_type' => OrderType::DINE_IN->value,
                'table_number' => str_repeat('T', 55),
                'items' => [['meal_id' => $this->meal2->id, 'quantity' => 1]],
            ]);
            $this->fail('Expected InvalidArgumentException on oversized table_number');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Table number cannot exceed 50 characters', $e->getMessage());
        }

        // Customer name > 150 chars
        try {
            $action->execute([
                'branch_id' => $this->branch->id,
                'cashier_id' => $this->cashier->id,
                'order_type' => OrderType::DINE_IN->value,
                'customer_name' => str_repeat('N', 155),
                'items' => [['meal_id' => $this->meal2->id, 'quantity' => 1]],
            ]);
            $this->fail('Expected InvalidArgumentException on oversized customer_name');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Customer name cannot exceed 150 characters', $e->getMessage());
        }

        // Notes > 500 chars
        try {
            $action->execute([
                'branch_id' => $this->branch->id,
                'cashier_id' => $this->cashier->id,
                'order_type' => OrderType::DINE_IN->value,
                'notes' => str_repeat('X', 505),
                'items' => [['meal_id' => $this->meal2->id, 'quantity' => 1]],
            ]);
            $this->fail('Expected InvalidArgumentException on oversized notes');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Notes cannot exceed 500 characters', $e->getMessage());
        }
    }

    public function test_checkout_rejects_empty_cart(): void
    {
        $action = new CreateOrderAction();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot create an order without items');

        $action->execute([
            'branch_id' => $this->branch->id,
            'cashier_id' => $this->cashier->id,
            'order_type' => OrderType::DINE_IN->value,
            'items' => [],
        ]);
    }

    public function test_payment_rejects_invalid_payment_method(): void
    {
        $createAction = new CreateOrderAction();
        $order = $createAction->execute([
            'branch_id' => $this->branch->id,
            'cashier_id' => $this->cashier->id,
            'order_type' => OrderType::DINE_IN->value,
            'items' => [
                ['meal_id' => $this->meal2->id, 'quantity' => 1],
            ],
        ]);

        $paymentAction = new ProcessPaymentAction();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid payment method');

        $paymentAction->execute($order, [
            'payment_method' => 'cryptocurrency_token',
            'amount_tendered' => 60.00,
        ]);
    }

    public function test_payment_rejects_oversized_reference_number(): void
    {
        $createAction = new CreateOrderAction();
        $order = $createAction->execute([
            'branch_id' => $this->branch->id,
            'cashier_id' => $this->cashier->id,
            'order_type' => OrderType::DINE_IN->value,
            'items' => [
                ['meal_id' => $this->meal2->id, 'quantity' => 1],
            ],
        ]);

        $paymentAction = new ProcessPaymentAction();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Reference number cannot exceed 100 characters');

        $oversizedRef = str_repeat('REF-99999-', 12); // 132 chars > 100 limit

        $paymentAction->execute($order, [
            'payment_method' => PaymentMethod::CARD->value,
            'amount_tendered' => 60.00,
            'reference_number' => $oversizedRef,
        ]);
    }

    public function test_payment_rejects_insufficient_cash_tender(): void
    {
        $createAction = new CreateOrderAction();
        $order = $createAction->execute([
            'branch_id' => $this->branch->id,
            'cashier_id' => $this->cashier->id,
            'order_type' => OrderType::DINE_IN->value,
            'items' => [
                ['meal_id' => $this->meal1->id, 'quantity' => 1], // 750.00
            ],
        ]);

        $paymentAction = new ProcessPaymentAction();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('is less than the total due');

        $paymentAction->execute($order, [
            'payment_method' => PaymentMethod::CASH->value,
            'amount_tendered' => 700.00, // 50.00 short
        ]);
    }

    public function test_checkout_modal_requires_reference_code_for_digital_or_card_methods(): void
    {
        $this->actingAs($this->cashier);

        $component = new CheckoutModal();
        $createAction = new CreateOrderAction();
        $paymentAction = new ProcessPaymentAction();

        $error = $component->processCheckoutFromClient([
            'items' => [
                ['meal_id' => $this->meal2->id, 'quantity' => 1],
            ],
            'order_type' => OrderType::DINE_IN->value,
            'payment_method' => PaymentMethod::CARD->value,
            'amount_tendered' => '60.00',
            'reference_number' => '', // Blank reference
        ], $createAction, $paymentAction);

        $this->assertStringContainsString('Reference / Approval Code is required', (string) $error);
    }
}
