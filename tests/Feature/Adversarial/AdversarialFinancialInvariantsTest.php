<?php

namespace Tests\Feature\Adversarial;

use App\Domains\Branches\Models\Branch;
use App\Domains\Identity\Models\User;
use App\Domains\Menu\Models\Meal;
use App\Domains\Menu\Models\MealCategory;
use App\Domains\PointOfSale\Actions\CreateOrderAction;
use App\Domains\PointOfSale\Models\Order;
use App\Support\Constants\SystemConstants;
use App\Support\Enums\StaffRole;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;
use Tests\TestCase;

class AdversarialFinancialInvariantsTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branch;
    protected User $cashier;
    protected MealCategory $category;
    protected Meal $mealA;
    protected Meal $mealB;
    protected Meal $mealOdd;
    protected CreateOrderAction $createOrderAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Cache::flush();

        $this->branch = Branch::firstOrCreate(['code' => 'B01'], [
            'name' => 'Flagship Manila',
            'is_active' => true,
        ]);

        $this->cashier = User::create([
            'name' => 'Cashier B01',
            'email' => 'csh_fin_b01@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'B01-CSH-FIN01',
            'pin_code' => Hash::make('1234'),
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);
        $this->cashier->assignRole(StaffRole::CASHIER->value);

        $this->category = MealCategory::firstOrCreate(['slug' => 'mains'], [
            'name' => 'Main Dishes',
            'is_active' => true,
        ]);

        $this->mealA = Meal::create([
            'name' => 'Classic Chicken Adobo',
            'menu_code' => 'B01-ADOB',
            'category_id' => $this->category->id,
            'branch_id' => $this->branch->id,
            'price' => 280.00,
            'is_available' => true,
            'stock_quantity' => 100,
            'track_stock' => true,
        ]);

        $this->mealB = Meal::create([
            'name' => 'Sinigang na Baboy',
            'menu_code' => 'B01-SINI',
            'category_id' => $this->category->id,
            'branch_id' => $this->branch->id,
            'price' => 350.00,
            'is_available' => true,
            'stock_quantity' => 100,
            'track_stock' => true,
        ]);

        $this->mealOdd = Meal::create([
            'name' => 'Odd Priced Item',
            'menu_code' => 'B01-ODD',
            'category_id' => $this->category->id,
            'branch_id' => $this->branch->id,
            'price' => 33.33,
            'is_available' => true,
            'stock_quantity' => 100,
            'track_stock' => true,
        ]);

        $this->createOrderAction = app(CreateOrderAction::class);
    }

    /**
     * Invariant 1: 12% BIR VAT calculations and centavo rounding on standard orders.
     */
    public function test_bir_12_percent_vat_formula_and_boundary_invariants(): void
    {
        $this->actingAs($this->cashier);

        // Standard order: subtotal = 280.00, no discount
        $order = $this->createOrderAction->execute([
            'branch_id' => $this->branch->id,
            'cashier_id' => $this->cashier->id,
            'order_type' => 'dine_in',
            'discount_type' => 'none',
            'items' => [
                ['meal_id' => $this->mealA->id, 'quantity' => 1],
            ],
        ]);

        // Net Sales (VATable) = 280.00 / 1.12 = 250.00
        $this->assertEquals(250.00, $order->vatable_sales);
        // VAT Amount (12%) = 250.00 * 0.12 = 30.00
        $this->assertEquals(30.00, $order->vat_amount);
        // VAT-Exempt = 0.00
        $this->assertEquals(0.00, $order->vat_exempt_sales);
        // Gross Invariant: Vatable + VAT == Total Amount
        $this->assertEquals($order->total_amount, round($order->vatable_sales + $order->vat_amount, 2));

        // Boundary test: Odd priced item (33.33 PHP)
        $oddOrder = $this->createOrderAction->execute([
            'branch_id' => $this->branch->id,
            'cashier_id' => $this->cashier->id,
            'order_type' => 'takeout',
            'discount_type' => 'none',
            'items' => [
                ['meal_id' => $this->mealOdd->id, 'quantity' => 1],
            ],
        ]);

        // 33.33 / 1.12 = 29.758928... -> 29.76
        $this->assertEquals(29.76, $oddOrder->vatable_sales);
        // 29.76 * 0.12 = 3.5712 -> 3.57
        $this->assertEquals(3.57, $oddOrder->vat_amount);
        // Invariant: sum 29.76 + 3.57 = 33.33
        $this->assertEquals(33.33, round($oddOrder->vatable_sales + $oddOrder->vat_amount, 2));
    }

    /**
     * Invariant 2: Senior Citizen / PWD 20% statutory discount and VAT exemption.
     */
    public function test_senior_citizen_pwd_20_percent_discount_and_vat_exemption(): void
    {
        $this->actingAs($this->cashier);

        // Subtotal = 280.00 + 350.00 = 630.00
        $order = $this->createOrderAction->execute([
            'branch_id' => $this->branch->id,
            'cashier_id' => $this->cashier->id,
            'order_type' => 'dine_in',
            'discount_type' => 'senior_pwd',
            'discount_amount' => 315.00, // Attacker forged 50% discount
            'items' => [
                ['meal_id' => $this->mealA->id, 'quantity' => 1],
                ['meal_id' => $this->mealB->id, 'quantity' => 1],
            ],
        ]);

        // Server MUST reject client discount and recompute 20% statutory discount:
        // 630.00 * 0.20 = 126.00
        $this->assertEquals(630.00, (float) $order->subtotal);
        $this->assertEquals(126.00, (float) $order->discount_amount);
        $this->assertEquals(504.00, (float) $order->total_amount);

        // Under BIR regulations, Senior/PWD sales are VAT-Exempt:
        // vatable_sales = 0.00, vat_amount = 0.00, vat_exempt_sales = 504.00
        $this->assertEquals(0.00, $order->vatable_sales);
        $this->assertEquals(0.00, $order->vat_amount);
        $this->assertEquals(504.00, $order->vat_exempt_sales);
    }

    /**
     * Invariant 3: Centavo boundary values: 0.01 PHP, 0.05 PHP, 0.99 PHP, 1000.01 PHP.
     */
    public function test_centavo_level_rounding_and_odd_amounts(): void
    {
        // 1 centavo
        $order01 = new Order(['total_amount' => 0.01, 'discount_amount' => 0.00]);
        // 0.01 / 1.12 = 0.0089... -> 0.01
        $this->assertEquals(0.01, $order01->vatable_sales);
        // 0.01 * 0.12 = 0.0012 -> 0.00
        $this->assertEquals(0.00, $order01->vat_amount);

        // 99.99 PHP
        $order99 = new Order(['total_amount' => 99.99, 'discount_amount' => 0.00]);
        // 99.99 / 1.12 = 89.2767... -> 89.28
        $this->assertEquals(89.28, $order99->vatable_sales);
        // 89.28 * 0.12 = 10.7136 -> 10.71
        $this->assertEquals(10.71, $order99->vat_amount);
        $this->assertEquals(99.99, round($order99->vatable_sales + $order99->vat_amount, 2));

        // 1000.01 PHP
        $order1000 = new Order(['total_amount' => 1000.01, 'discount_amount' => 0.00]);
        // 1000.01 / 1.12 = 892.866... -> 892.87
        $this->assertEquals(892.87, $order1000->vatable_sales);
        // 892.87 * 0.12 = 107.1444 -> 107.14
        $this->assertEquals(107.14, $order1000->vat_amount);
        $this->assertEquals(1000.01, round($order1000->vatable_sales + $order1000->vat_amount, 2));
    }

    /**
     * Invariant 4: Tax-exempt classification when discount_amount > 0.
     */
    public function test_discount_order_tax_classification(): void
    {
        $discountedOrder = new Order(['total_amount' => 500.00, 'discount_amount' => 50.00]);
        $this->assertEquals(0.00, $discountedOrder->vatable_sales);
        $this->assertEquals(0.00, $discountedOrder->vat_amount);
        $this->assertEquals(500.00, $discountedOrder->vat_exempt_sales);
        $this->assertEquals(0.00, $discountedOrder->zero_rated_sales);

        $undiscountedOrder = new Order(['total_amount' => 500.00, 'discount_amount' => 0.00]);
        $this->assertEquals(round(500.00 / 1.12, 2), $undiscountedOrder->vatable_sales);
        $this->assertEquals(round($undiscountedOrder->vatable_sales * 0.12, 2), $undiscountedOrder->vat_amount);
        $this->assertEquals(0.00, $undiscountedOrder->vat_exempt_sales);
    }
}
