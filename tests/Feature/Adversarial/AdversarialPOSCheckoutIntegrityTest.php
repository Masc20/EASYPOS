<?php

namespace Tests\Feature\Adversarial;

use App\Domains\Branches\Models\Branch;
use App\Domains\Identity\Models\User;
use App\Domains\Menu\Models\Meal;
use App\Domains\Menu\Models\MealCategory;
use App\Domains\PointOfSale\Actions\CreateOrderAction;
use App\Domains\PointOfSale\Models\Order;
use App\Domains\PointOfSale\Models\OrderItem;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class AdversarialPOSCheckoutIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branch1;
    protected Branch $branch2;
    protected User $cashier1;
    protected MealCategory $category;
    protected CreateOrderAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->branch1 = Branch::where('code', 'B01')->firstOrFail();
        $this->branch2 = Branch::where('code', 'B02')->firstOrFail();

        $this->cashier1 = User::factory()->create([
            'branch_id' => $this->branch1->id,
            'name' => 'Cashier POS 1',
            'email' => 'cashier_pos1@test.com',
        ]);
        $this->cashier1->assignRole('cashier');

        $this->category = MealCategory::create([
            'name' => 'Main Courses',
            'slug' => 'main-courses',
            'is_active' => true,
            'display_order' => 1,
        ]);

        $this->action = app(CreateOrderAction::class);
    }

    public function test_checkout_rejects_86d_sold_out_dish_and_rolls_back_atomically(): void
    {
        $soldOutMeal = Meal::create([
            'branch_id' => $this->branch1->id,
            'category_id' => $this->category->id,
            'name' => 'Sold Out Crispy Pata',
            'price' => 650.00,
            'is_available' => false,
        ]);

        $initialOrdersCount = Order::count();
        $initialOrderItemsCount = OrderItem::count();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Meal 'Sold Out Crispy Pata' is marked as 86'd / Sold Out.");

        try {
            $this->action->execute([
                'branch_id' => $this->branch1->id,
                'cashier_id' => $this->cashier1->id,
                'order_type' => 'dine_in',
                'table_number' => 'T-01',
                'items' => [
                    [
                        'meal_id' => $soldOutMeal->id,
                        'quantity' => 1,
                    ],
                ],
            ]);
        } finally {
            $this->assertEquals($initialOrdersCount, Order::count(), 'No order should be created when dish is sold out.');
            $this->assertEquals($initialOrderItemsCount, OrderItem::count(), 'No line items should be created when dish is sold out.');
        }
    }

    public function test_checkout_rejects_dish_with_zero_stock_and_rolls_back(): void
    {
        $zeroStockMeal = Meal::create([
            'branch_id' => $this->branch1->id,
            'category_id' => $this->category->id,
            'name' => 'Zero Stock Ribs',
            'price' => 450.00,
            'is_available' => true,
            'track_stock' => true,
            'stock_quantity' => 0,
        ]);

        $initialOrdersCount = Order::count();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Meal 'Zero Stock Ribs' is marked as 86'd / Sold Out.");

        try {
            $this->action->execute([
                'branch_id' => $this->branch1->id,
                'cashier_id' => $this->cashier1->id,
                'order_type' => 'dine_in',
                'items' => [
                    [
                        'meal_id' => $zeroStockMeal->id,
                        'quantity' => 1,
                    ],
                ],
            ]);
        } finally {
            $this->assertEquals($initialOrdersCount, Order::count());
            $this->assertEquals(0, $zeroStockMeal->fresh()->stock_quantity);
        }
    }

    public function test_checkout_rejects_insufficient_stock_and_preserves_stock_integrity(): void
    {
        $limitedMeal = Meal::create([
            'branch_id' => $this->branch1->id,
            'category_id' => $this->category->id,
            'name' => 'Limited Wagyu Steak',
            'price' => 1200.00,
            'is_available' => true,
            'track_stock' => true,
            'stock_quantity' => 2,
        ]);

        $initialOrdersCount = Order::count();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Insufficient portions left for 'Limited Wagyu Steak'. Only 2 available.");

        try {
            $this->action->execute([
                'branch_id' => $this->branch1->id,
                'cashier_id' => $this->cashier1->id,
                'order_type' => 'dine_in',
                'items' => [
                    [
                        'meal_id' => $limitedMeal->id,
                        'quantity' => 3, // Requesting 3 when only 2 available
                    ],
                ],
            ]);
        } finally {
            $this->assertEquals($initialOrdersCount, Order::count());
            $this->assertEquals(2, $limitedMeal->fresh()->stock_quantity, 'Stock quantity must remain unchanged after failed checkout.');
        }
    }

    public function test_multi_item_order_atomic_rollback_when_one_item_fails_stock_check(): void
    {
        $meal1 = Meal::create([
            'branch_id' => $this->branch1->id,
            'category_id' => $this->category->id,
            'name' => 'Meal One In Stock',
            'price' => 200.00,
            'is_available' => true,
            'track_stock' => true,
            'stock_quantity' => 10,
        ]);

        $meal2 = Meal::create([
            'branch_id' => $this->branch1->id,
            'category_id' => $this->category->id,
            'name' => 'Meal Two In Stock',
            'price' => 300.00,
            'is_available' => true,
            'track_stock' => true,
            'stock_quantity' => 5,
        ]);

        $meal3 = Meal::create([
            'branch_id' => $this->branch1->id,
            'category_id' => $this->category->id,
            'name' => 'Meal Three Sold Out',
            'price' => 250.00,
            'is_available' => false,
        ]);

        $initialOrdersCount = Order::count();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Meal 'Meal Three Sold Out' is marked as 86'd / Sold Out.");

        try {
            $this->action->execute([
                'branch_id' => $this->branch1->id,
                'cashier_id' => $this->cashier1->id,
                'order_type' => 'dine_in',
                'items' => [
                    ['meal_id' => $meal1->id, 'quantity' => 2],
                    ['meal_id' => $meal2->id, 'quantity' => 1],
                    ['meal_id' => $meal3->id, 'quantity' => 1],
                ],
            ]);
        } finally {
            $this->assertEquals($initialOrdersCount, Order::count(), 'Zero orders must be persisted.');
            $this->assertEquals(10, $meal1->fresh()->stock_quantity, 'Meal 1 stock must NOT be decremented.');
            $this->assertEquals(5, $meal2->fresh()->stock_quantity, 'Meal 2 stock must NOT be decremented.');
        }
    }

    public function test_consolidated_quantities_across_multiple_cart_lines_for_same_meal(): void
    {
        $meal = Meal::create([
            'branch_id' => $this->branch1->id,
            'category_id' => $this->category->id,
            'name' => 'Split Portion Curry',
            'price' => 180.00,
            'is_available' => true,
            'track_stock' => true,
            'stock_quantity' => 5,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Insufficient portions left for 'Split Portion Curry'. Only 5 available.");

        try {
            $this->action->execute([
                'branch_id' => $this->branch1->id,
                'cashier_id' => $this->cashier1->id,
                'order_type' => 'dine_in',
                'items' => [
                    ['meal_id' => $meal->id, 'quantity' => 3, 'special_instructions' => 'Mild spicy'],
                    ['meal_id' => $meal->id, 'quantity' => 3, 'special_instructions' => 'Extra spicy'],
                ], // 3 + 3 = 6 > 5
            ]);
        } finally {
            $this->assertEquals(5, $meal->fresh()->stock_quantity, 'Stock must remain intact.');
            $this->assertEquals(0, Order::count());
        }
    }

    public function test_successful_checkout_deducts_stock_and_auto_marks_sold_out_at_zero(): void
    {
        $meal = Meal::create([
            'branch_id' => $this->branch1->id,
            'category_id' => $this->category->id,
            'name' => 'Last Servings Kare-Kare',
            'price' => 350.00,
            'is_available' => true,
            'track_stock' => true,
            'stock_quantity' => 3,
        ]);

        $order = $this->action->execute([
            'branch_id' => $this->branch1->id,
            'cashier_id' => $this->cashier1->id,
            'order_type' => 'dine_in',
            'items' => [
                ['meal_id' => $meal->id, 'quantity' => 3],
            ],
        ]);

        $this->assertInstanceOf(Order::class, $order);
        $freshMeal = $meal->fresh();
        $this->assertEquals(0, $freshMeal->stock_quantity, 'Stock quantity must be exactly 0 after buying remaining items.');
        $this->assertFalse($freshMeal->is_available, 'Meal must automatically be marked unavailable when stock reaches 0.');

        // Immediate subsequent attempt to buy more must be rejected
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Meal 'Last Servings Kare-Kare' is marked as 86'd / Sold Out.");

        $this->action->execute([
            'branch_id' => $this->branch1->id,
            'cashier_id' => $this->cashier1->id,
            'order_type' => 'dine_in',
            'items' => [
                ['meal_id' => $meal->id, 'quantity' => 1],
            ],
        ]);
    }

    public function test_checkout_enforces_strict_branch_scoping(): void
    {
        $mealB2 = Meal::create([
            'branch_id' => $this->branch2->id,
            'category_id' => $this->category->id,
            'name' => 'Cebu Exclusive Lechon',
            'price' => 500.00,
            'is_available' => true,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Meal 'Cebu Exclusive Lechon' is not available for this branch.");

        $this->action->execute([
            'branch_id' => $this->branch1->id,
            'cashier_id' => $this->cashier1->id,
            'order_type' => 'dine_in',
            'items' => [
                ['meal_id' => $mealB2->id, 'quantity' => 1],
            ],
        ]);
    }

    public function test_invalid_quantities_and_payload_tampering_are_strictly_rejected(): void
    {
        $meal = Meal::create([
            'branch_id' => $this->branch1->id,
            'category_id' => $this->category->id,
            'name' => 'Normal Dish',
            'price' => 100.00,
            'is_available' => true,
        ]);

        $tamperedPayloads = [
            'empty_items' => ['items' => []],
            'negative_qty' => ['items' => [['meal_id' => $meal->id, 'quantity' => -1]]],
            'zero_qty' => ['items' => [['meal_id' => $meal->id, 'quantity' => 0]]],
            'fractional_qty' => ['items' => [['meal_id' => $meal->id, 'quantity' => 1.5]]],
            'exorbitant_qty' => ['items' => [['meal_id' => $meal->id, 'quantity' => 1001]]],
            'invalid_order_type' => ['order_type' => 'space_flight', 'items' => [['meal_id' => $meal->id, 'quantity' => 1]]],
        ];

        foreach ($tamperedPayloads as $label => $payload) {
            $fullPayload = array_merge([
                'branch_id' => $this->branch1->id,
                'cashier_id' => $this->cashier1->id,
                'order_type' => 'dine_in',
            ], $payload);

            try {
                $this->action->execute($fullPayload);
                $this->fail("Expected InvalidArgumentException for tampered payload [{$label}]");
            } catch (InvalidArgumentException $e) {
                $this->assertNotEmpty($e->getMessage(), "Payload [{$label}] was rejected as expected.");
            }
        }
    }
}
