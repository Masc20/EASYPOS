<?php

namespace Tests\Feature\Performance;

use App\Domains\Identity\Models\User;
use App\Domains\Menu\Models\Meal;
use App\Domains\Menu\Models\MealCategory;
use App\Domains\PointOfSale\Actions\CreateOrderAction;
use App\Domains\PointOfSale\Actions\ProcessPaymentAction;
use App\Domains\PointOfSale\Events\KitchenTicketDispatched;
use App\Domains\PointOfSale\Events\OrderCreated;
use App\Domains\PointOfSale\Events\PaymentProcessed;
use App\Domains\PointOfSale\Livewire\Checkout\CheckoutModal;
use App\Domains\PointOfSale\Models\Order;
use App\Domains\PointOfSale\Models\OrderItem;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

class CheckoutAtomicityAndBulkWriteTest extends TestCase
{
    use RefreshDatabase;

    protected User $cashier;
    protected MealCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(MenuSeeder::class);

        $this->cashier = User::where('emp_id', 'B01-CSH-001')->firstOrFail();
        $this->category = MealCategory::firstOrFail();
    }

    /**
     * Scenario 1:
     * Checkout rolls back entire order and restores stock if payment fails.
     * Guarantees single outer atomic transaction semantics: no orphan unpaid order,
     * no order items, stock restored, and no uncommitted events dispatched.
     */
    public function test_checkout_rolls_back_entire_order_and_restores_stock_if_payment_fails(): void
    {
        Event::fake([OrderCreated::class, PaymentProcessed::class, KitchenTicketDispatched::class]);

        $dishA = Meal::create([
            'category_id' => $this->category->id,
            'branch_id' => 1,
            'menu_code' => 'TEST-STOCK-1',
            'name' => 'Atomic Stock Burger',
            'price' => 150.00,
            'track_stock' => true,
            'stock_quantity' => 10,
            'is_available' => true,
            'is_active' => true,
        ]);

        // Simulate payment failure during ProcessPaymentAction
        $this->mock(ProcessPaymentAction::class, function ($mock) {
            $mock->shouldReceive('execute')
                ->once()
                ->andThrow(new \RuntimeException('Payment processing failed: Simulated payment timeout.'));
        });

        Livewire::actingAs($this->cashier)
            ->test(CheckoutModal::class)
            ->dispatch('open-checkout', [
                'items' => [
                    [
                        'meal_id' => $dishA->id,
                        'name' => $dishA->name,
                        'menu_code' => $dishA->menu_code,
                        'price' => 150.00,
                        'quantity' => 4,
                    ],
                ],
                'order_type' => 'dine_in',
                'table_number' => 'T-01',
                'customer_name' => 'Charlie Test',
                'subtotal' => 600.00,
                'discount_amount' => 0.00,
                'total_amount' => 600.00,
            ])
            ->set('amountTendered', '600.00')
            ->call('processCheckout')
            ->assertSet('errorMessage', 'Payment processing failed: Simulated payment timeout.')
            ->assertNotDispatched('show-receipt')
            ->assertNotDispatched('cart-reset');

        // Verify total database rollback: no orphan order, no items, restored stock
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('kitchen_tickets', 0);

        $dishA->refresh();
        $this->assertSame(10, $dishA->stock_quantity, 'Tracked stock must be restored to initial quantity on rollback.');
        $this->assertTrue($dishA->is_available);

        // Verify deferred events were never dispatched due to rollback
        Event::assertNotDispatched(OrderCreated::class);
        Event::assertNotDispatched(PaymentProcessed::class);
        Event::assertNotDispatched(KitchenTicketDispatched::class);
    }

    /**
     * Scenario 2:
     * Checkout bulk inserts order items in a single SQL query.
     * Replaces N individual inserts with 1 batch insert while ensuring proper
     * JSON cast for selected_options and created_at timestamps.
     */
    public function test_checkout_bulk_inserts_order_items_in_single_query(): void
    {
        $meals = [];
        for ($i = 1; $i <= 8; $i++) {
            $meals[] = Meal::create([
                'category_id' => $this->category->id,
                'branch_id' => 1,
                'menu_code' => "BULK-{$i}",
                'name' => "Bulk Dish {$i}",
                'price' => 100.00 + ($i * 10),
                'track_stock' => false,
                'is_available' => true,
                'is_active' => true,
            ]);
        }

        $items = [];
        foreach ($meals as $index => $meal) {
            $items[] = [
                'meal_id' => $meal->id,
                'quantity' => $index + 1,
                'special_instructions' => "Instruction for item {$index}",
                'selected_options' => [
                    'spice' => 'medium',
                    'line_index' => $index,
                ],
            ];
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $action = app(CreateOrderAction::class);
        $order = $action->execute([
            'branch_id' => 1,
            'cashier_id' => $this->cashier->id,
            'order_type' => 'dine_in',
            'table_number' => 'T-08',
            'customer_name' => 'Bulk Insert Customer',
            'discount_type' => 'none',
            'items' => $items,
        ]);

        $queryLog = DB::getQueryLog();
        DB::disableQueryLog();

        // Filter queries inserting into order_items
        $insertQueries = array_filter($queryLog, function ($entry) {
            $sql = strtolower($entry['query']);
            return str_contains($sql, 'insert into') && str_contains($sql, 'order_items');
        });

        $this->assertCount(
            1,
            $insertQueries,
            'Expected exactly 1 bulk INSERT SQL query for all 8 order_items.'
        );

        $this->assertDatabaseCount('order_items', 8);

        // Verify timestamps and JSON casting on all persisted items
        $persistedItems = OrderItem::where('order_id', $order->id)->orderBy('id')->get();
        $this->assertCount(8, $persistedItems);

        foreach ($persistedItems as $idx => $item) {
            $this->assertInstanceOf(Carbon::class, $item->created_at);
            $this->assertInstanceOf(Carbon::class, $item->updated_at);
            $this->assertIsArray($item->selected_options);
            $this->assertSame('medium', $item->selected_options['spice'] ?? null);
            $this->assertSame($idx, $item->selected_options['line_index'] ?? null);
            $this->assertSame("Instruction for item {$idx}", $item->special_instructions);
            $this->assertSame($idx + 1, $item->quantity);
        }
    }

    /**
     * Scenario 3:
     * Checkout groups stock decrements for duplicate and multiple items.
     * Consolidates quantities per distinct meal_id so multi-line entries
     * execute a single consolidated stock update query.
     */
    public function test_checkout_groups_stock_decrements_for_duplicate_and_multiple_items(): void
    {
        $dishA = Meal::create([
            'category_id' => $this->category->id,
            'branch_id' => 1,
            'menu_code' => 'GRP-A',
            'name' => 'Consolidated Dish A',
            'price' => 120.00,
            'track_stock' => true,
            'stock_quantity' => 20,
            'is_available' => true,
            'is_active' => true,
        ]);

        $dishB = Meal::create([
            'category_id' => $this->category->id,
            'branch_id' => 1,
            'menu_code' => 'GRP-B',
            'name' => 'Consolidated Dish B',
            'price' => 200.00,
            'track_stock' => true,
            'stock_quantity' => 15,
            'is_available' => true,
            'is_active' => true,
        ]);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $action = app(CreateOrderAction::class);
        $order = $action->execute([
            'branch_id' => 1,
            'cashier_id' => $this->cashier->id,
            'order_type' => 'takeout',
            'customer_name' => 'Group Decrement Customer',
            'discount_type' => 'none',
            'items' => [
                ['meal_id' => $dishA->id, 'quantity' => 2, 'special_instructions' => 'Line 1: no onion'],
                ['meal_id' => $dishB->id, 'quantity' => 4, 'special_instructions' => 'Line 2: regular'],
                ['meal_id' => $dishA->id, 'quantity' => 3, 'special_instructions' => 'Line 3: extra cheese'],
            ],
        ]);

        $queryLog = DB::getQueryLog();
        DB::disableQueryLog();

        // Assert Dish A decremented by 5 (2 + 3) and Dish B by 4 in consolidated updates
        $this->assertSame(15, $dishA->fresh()->stock_quantity); // 20 - (2 + 3) = 15
        $this->assertSame(11, $dishB->fresh()->stock_quantity); // 15 - 4 = 11

        // Verify update queries on meals table: exactly 2 updates (1 for Dish A, 1 for Dish B)
        $mealUpdateQueries = array_filter($queryLog, function ($entry) {
            $sql = strtolower($entry['query']);
            return str_contains($sql, 'update') && str_contains($sql, 'meals');
        });

        $this->assertCount(
            2,
            $mealUpdateQueries,
            'Expected exactly 2 UPDATE queries for 2 distinct dishes, consolidating 3 line items.'
        );

        // Verify order has 3 distinct line items preserved for kitchen/cashier clarity
        $this->assertSame(3, OrderItem::where('order_id', $order->id)->count());
    }

    /**
     * Scenario 4:
     * Checkout sorts meal IDs ascending prior to acquiring pessimistic row locks,
     * eliminating MySQL InnoDB deadlock risk (ERROR 1213).
     */
    public function test_checkout_sorts_meal_ids_to_prevent_deadlocks(): void
    {
        $meals = [];
        for ($i = 1; $i <= 4; $i++) {
            $meals[] = Meal::create([
                'category_id' => $this->category->id,
                'branch_id' => 1,
                'menu_code' => "SORT-{$i}",
                'name' => "Deadlock Sort Dish {$i}",
                'price' => 80.00,
                'track_stock' => false,
                'is_available' => true,
                'is_active' => true,
            ]);
        }

        // Deliberately pass meals in non-ascending order (reverse order)
        $passedMealIds = [
            $meals[3]->id,
            $meals[1]->id,
            $meals[2]->id,
            $meals[0]->id,
        ];
        $expectedAscendingIds = collect($passedMealIds)->sort()->values()->all();

        DB::flushQueryLog();
        DB::enableQueryLog();

        $action = app(CreateOrderAction::class);
        $order = $action->execute([
            'branch_id' => 1,
            'cashier_id' => $this->cashier->id,
            'order_type' => 'dine_in',
            'table_number' => 'T-04',
            'items' => [
                ['meal_id' => $passedMealIds[0], 'quantity' => 1],
                ['meal_id' => $passedMealIds[1], 'quantity' => 1],
                ['meal_id' => $passedMealIds[2], 'quantity' => 1],
                ['meal_id' => $passedMealIds[3], 'quantity' => 1],
            ],
        ]);

        $queryLog = DB::getQueryLog();
        DB::disableQueryLog();

        // Find the meal lock/select query
        $mealLockQueries = array_filter($queryLog, function ($entry) {
            $sql = strtolower($entry['query']);
            return str_contains($sql, 'from') && str_contains($sql, 'meals') && str_contains($sql, 'where') && str_contains($sql, 'id');
        });

        $this->assertNotEmpty($mealLockQueries, 'Lock query for meals was not logged.');
        $lockQuery = reset($mealLockQueries);

        // Verify the bindings for meal IDs are strictly in ascending order
        $mealIdBindings = array_values(array_filter($lockQuery['bindings'], fn ($val) => is_numeric($val) && in_array((int) $val, $passedMealIds, true)));
        $mealIdBindingsInt = array_map('intval', $mealIdBindings);

        $this->assertSame(
            $expectedAscendingIds,
            $mealIdBindingsInt,
            'Meal IDs must be sorted ascending in query bindings to guarantee deterministic lock hierarchy.'
        );

        // Verify explicit ORDER BY id ASC in SQL query
        $this->assertMatchesRegularExpression(
            '/order by [`"]?id[`"]? asc/i',
            $lockQuery['query'],
            'Locking query must include ORDER BY id ASC to eliminate InnoDB deadlocks.'
        );
    }

    /**
     * Additional edge case:
     * Consolidated quantity exceeding stock throws InvalidArgumentException
     * without modifying database or creating partial orders.
     */
    public function test_checkout_fails_atomically_when_consolidated_quantity_exceeds_stock(): void
    {
        $dish = Meal::create([
            'category_id' => $this->category->id,
            'branch_id' => 1,
            'menu_code' => 'EXCEED-1',
            'name' => 'Limited Stock Dish',
            'price' => 100.00,
            'track_stock' => true,
            'stock_quantity' => 5,
            'is_available' => true,
            'is_active' => true,
        ]);

        $action = app(CreateOrderAction::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Insufficient portions left for '{$dish->name}'. Only 5 available.");

        // Two lines requesting 3 + 3 = 6 portions (exceeds stock of 5)
        $action->execute([
            'branch_id' => 1,
            'cashier_id' => $this->cashier->id,
            'order_type' => 'dine_in',
            'table_number' => 'T-01',
            'items' => [
                ['meal_id' => $dish->id, 'quantity' => 3],
                ['meal_id' => $dish->id, 'quantity' => 3],
            ],
        ]);

        // Stock remains untouched
        $this->assertSame(5, $dish->fresh()->stock_quantity);
        $this->assertDatabaseCount('orders', 0);
    }
}
