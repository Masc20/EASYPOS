<?php

namespace Tests\Feature\Adversarial;

use App\Domains\Branches\Models\Branch;
use App\Domains\Identity\Models\User;
use App\Domains\Kitchen\Models\KitchenTicket;
use App\Domains\Menu\Actions\ToggleMealAvailabilityAction;
use App\Domains\Menu\Events\MealAvailabilityToggled;
use App\Domains\Menu\Models\Meal;
use App\Domains\Menu\Models\MealCategory;
use App\Domains\PointOfSale\Actions\CreateOrderAction;
use App\Domains\PointOfSale\Actions\ProcessPaymentAction;
use App\Domains\PointOfSale\Livewire\Checkout\CheckoutCart;
use App\Domains\PointOfSale\Livewire\Checkout\CheckoutModal;
use App\Domains\PointOfSale\Models\Order;
use App\Domains\PointOfSale\Models\OrderItem;
use App\Domains\PointOfSale\Models\Payment;
use App\Support\Enums\OrderStatus;
use App\Support\Enums\OrderType;
use App\Support\Enums\PaymentMethod;
use App\Support\Enums\PaymentStatus;
use App\Support\Enums\StaffRole;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Events\TransactionRolledBack;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;
use Livewire\Livewire;
use PDOException;
use Tests\TestCase;

class AdversarialConcurrencyRetryAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branch;
    protected User $cashier;
    protected User $chef;
    protected MealCategory $category;
    protected Meal $mealA;
    protected Meal $mealB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->branch = Branch::firstOrCreate(['code' => 'B01'], [
            'name' => 'Flagship Manila',
            'is_active' => true,
        ]);

        $this->category = MealCategory::firstOrCreate(['slug' => 'rice-meals'], [
            'name' => 'Rice Meals',
            'is_active' => true,
        ]);

        $this->cashier = User::create([
            'name' => 'Adversarial Cashier',
            'email' => 'adv_csh@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'B01-CSH-ADV1',
            'pin_code' => Hash::make('1234'),
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);
        $this->cashier->assignRole(StaffRole::CASHIER->value);

        $this->chef = User::create([
            'name' => 'Adversarial Chef',
            'email' => 'adv_chf@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'B01-CHF-ADV1',
            'pin_code' => Hash::make('5678'),
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);
        $this->chef->assignRole(StaffRole::CHEF->value);

        $this->mealA = Meal::create([
            'name' => 'Crispy Sisig with Rice',
            'menu_code' => 'B01-SISIG',
            'category_id' => $this->category->id,
            'branch_id' => $this->branch->id,
            'price' => 180.00,
            'track_stock' => true,
            'stock_quantity' => 10,
            'is_available' => true,
        ]);

        $this->mealB = Meal::create([
            'name' => 'Beef Kare-Kare',
            'menu_code' => 'B01-KARE',
            'category_id' => $this->category->id,
            'branch_id' => $this->branch->id,
            'price' => 250.00,
            'track_stock' => true,
            'stock_quantity' => 5,
            'is_available' => true,
        ]);
    }

    // =========================================================================
    // SECTION 1: CONCURRENT ORDER CREATION & SEQUENCE COLLISION RETRY LOOP
    // =========================================================================

    public function test_order_creation_recovers_cleanly_from_simulated_sequence_collision_on_retry(): void
    {
        $this->actingAs($this->cashier);

        $attempts = 0;
        $dateStr = now()->format('Ymd');
        $conflictingNumber = "B01-{$dateStr}-0001";

        // Listen for the rollback of Attempt 1: simulate that the concurrent transaction
        // (Process A) committed its conflicting order while Attempt 1 rolled back.
        Event::listen(TransactionRolledBack::class, function () use (&$attempts, $conflictingNumber) {
            if ($attempts === 1 && ! Order::where('order_number', $conflictingNumber)->exists()) {
                DB::table('orders')->insert([
                    'order_number' => $conflictingNumber,
                    'branch_id' => $this->branch->id,
                    'cashier_id' => $this->cashier->id,
                    'order_type' => OrderType::DINE_IN->value,
                    'subtotal' => 180.00,
                    'total_amount' => 180.00,
                    'status' => OrderStatus::PENDING->value,
                    'payment_status' => PaymentStatus::UNPAID->value,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        // Trigger a unique collision exception on Attempt 1
        Order::creating(function (Order $order) use (&$attempts) {
            $attempts++;
            if ($attempts === 1) {
                $pdoException = new PDOException("SQLSTATE[23000]: Integrity constraint violation: 19 UNIQUE constraint failed: orders.order_number");
                throw new QueryException('sqlite', 'insert into orders...', [], $pdoException);
            }
        });

        $action = new CreateOrderAction();
        $order = $action->execute([
            'branch_id' => $this->branch->id,
            'cashier_id' => $this->cashier->id,
            'order_type' => OrderType::DINE_IN->value,
            'items' => [
                ['meal_id' => $this->mealA->id, 'quantity' => 1],
            ],
        ]);

        // Attempt 1 collided, Attempt 2 cleanly succeeded!
        $this->assertEquals(2, $attempts, 'Expected exactly 2 attempts before success');
        $this->assertNotNull($order);

        // Verify two orders exist in the database with sequential numbers
        $orders = Order::orderBy('id')->get();
        $this->assertCount(2, $orders);

        $expectedFirst = "B01-{$dateStr}-0001";
        $expectedSecond = "B01-{$dateStr}-0002";

        $this->assertEquals($expectedFirst, $orders[0]->order_number);
        $this->assertEquals($expectedSecond, $orders[1]->order_number);
        $this->assertEquals($expectedSecond, $order->order_number);

        // Verify order items exist for the successfully retried order
        $this->assertCount(1, $order->items);
        $this->assertEquals($this->mealA->id, $order->items->first()->meal_id);
    }

    public function test_order_creation_recovers_after_multiple_consecutive_collisions(): void
    {
        $this->actingAs($this->cashier);

        $attempts = 0;
        $dateStr = now()->format('Ymd');

        // Two collisions injected:
        // Attempt 1 collides -> Process A inserts 0001
        // Attempt 2 collides -> Process B inserts 0002
        // Attempt 3 succeeds -> inserts 0003
        Event::listen(TransactionRolledBack::class, function () use (&$attempts, $dateStr) {
            if ($attempts === 1) {
                $num = "B01-{$dateStr}-0001";
                if (! Order::where('order_number', $num)->exists()) {
                    DB::table('orders')->insert([
                        'order_number' => $num,
                        'branch_id' => $this->branch->id,
                        'cashier_id' => $this->cashier->id,
                        'order_type' => OrderType::DINE_IN->value,
                        'subtotal' => 180.00,
                        'total_amount' => 180.00,
                        'status' => OrderStatus::PENDING->value,
                        'payment_status' => PaymentStatus::UNPAID->value,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            } elseif ($attempts === 2) {
                $num = "B01-{$dateStr}-0002";
                if (! Order::where('order_number', $num)->exists()) {
                    DB::table('orders')->insert([
                        'order_number' => $num,
                        'branch_id' => $this->branch->id,
                        'cashier_id' => $this->cashier->id,
                        'order_type' => OrderType::DINE_IN->value,
                        'subtotal' => 180.00,
                        'total_amount' => 180.00,
                        'status' => OrderStatus::PENDING->value,
                        'payment_status' => PaymentStatus::UNPAID->value,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        });

        Order::creating(function (Order $order) use (&$attempts) {
            $attempts++;
            if ($attempts <= 2) {
                $pdoException = new PDOException("SQLSTATE[23000]: Integrity constraint violation: 19 UNIQUE constraint failed: orders.order_number");
                throw new QueryException('sqlite', 'insert into orders...', [], $pdoException);
            }
        });

        $action = new CreateOrderAction();
        $order = $action->execute([
            'branch_id' => $this->branch->id,
            'cashier_id' => $this->cashier->id,
            'order_type' => OrderType::DINE_IN->value,
            'items' => [
                ['meal_id' => $this->mealA->id, 'quantity' => 1],
            ],
        ]);

        $this->assertEquals(3, $attempts, 'Expected exactly 3 attempts before success');
        $this->assertNotNull($order);

        // Total 3 orders in database: 0001, 0002, 0003
        $orders = Order::orderBy('id')->get();
        $this->assertCount(3, $orders);

        $this->assertEquals("B01-{$dateStr}-0001", $orders[0]->order_number);
        $this->assertEquals("B01-{$dateStr}-0002", $orders[1]->order_number);
        $this->assertEquals("B01-{$dateStr}-0003", $orders[2]->order_number);
        $this->assertEquals("B01-{$dateStr}-0003", $order->order_number);
    }

    public function test_order_creation_exhausts_retries_and_fails_safely_when_max_attempts_exceeded(): void
    {
        $this->actingAs($this->cashier);

        $attempts = 0;
        Order::creating(function (Order $order) use (&$attempts) {
            $attempts++;
            $pdoException = new PDOException("SQLSTATE[23000]: Integrity constraint violation: 19 UNIQUE constraint failed: orders.order_number");
            throw new QueryException('sqlite', 'insert into orders...', [], $pdoException);
        });

        $action = new CreateOrderAction();

        $this->expectException(QueryException::class);

        try {
            $action->execute([
                'branch_id' => $this->branch->id,
                'cashier_id' => $this->cashier->id,
                'order_type' => OrderType::DINE_IN->value,
                'items' => [
                    ['meal_id' => $this->mealA->id, 'quantity' => 1],
                ],
            ]);
        } finally {
            $this->assertEquals(3, $attempts, 'Must have attempted exactly 3 times before failing');
            $this->assertEquals(0, Order::count());
        }
    }

    public function test_non_collision_query_exceptions_are_not_retried_and_bubble_immediately(): void
    {
        $this->actingAs($this->cashier);

        $attemptsSeen = 0;
        Order::creating(function (Order $order) use (&$attemptsSeen) {
            $attemptsSeen++;
            // Force a non-order_number DB exception
            $pdoException = new PDOException("SQLSTATE[HY000]: General error: 1 no such table: non_existent_table");
            throw new QueryException('sqlite', 'SELECT * FROM non_existent_table', [], $pdoException);
        });

        $action = new CreateOrderAction();

        try {
            $action->execute([
                'branch_id' => $this->branch->id,
                'cashier_id' => $this->cashier->id,
                'order_type' => OrderType::DINE_IN->value,
                'items' => [
                    ['meal_id' => $this->mealA->id, 'quantity' => 1],
                ],
            ]);
            $this->fail('Expected QueryException from non-collision error.');
        } catch (QueryException $e) {
            // Must have failed immediately on attempt 1 without retrying
            $this->assertEquals(1, $attemptsSeen, 'Non-collision errors must NOT be retried.');
        }
    }

    public function test_rapid_burst_order_generation_maintains_strict_sequential_order_numbers(): void
    {
        $this->actingAs($this->cashier);
        $action = new CreateOrderAction();
        $dateStr = now()->format('Ymd');

        $createdOrders = [];
        for ($i = 1; $i <= 10; $i++) {
            $order = $action->execute([
                'branch_id' => $this->branch->id,
                'cashier_id' => $this->cashier->id,
                'order_type' => OrderType::DINE_IN->value,
                'items' => [
                    ['meal_id' => $this->mealA->id, 'quantity' => 1],
                ],
            ]);
            $createdOrders[] = $order;
        }

        $this->assertCount(10, $createdOrders);
        for ($i = 1; $i <= 10; $i++) {
            $expectedSeq = str_pad((string) $i, 4, '0', STR_PAD_LEFT);
            $this->assertEquals("B01-{$dateStr}-{$expectedSeq}", $createdOrders[$i - 1]->order_number);
        }
    }

    // =========================================================================
    // SECTION 2: REAL-TIME MEAL AVAILABILITY TOGGLING & CART GUARD RACES
    // =========================================================================

    public function test_cart_guard_detects_86d_dish_and_blocks_proceed_to_checkout(): void
    {
        $this->actingAs($this->cashier);

        // 1. Dish is available, cashier adds it to cart
        $cart = Livewire::actingAs($this->cashier)
            ->test(CheckoutCart::class)
            ->dispatch('add-to-cart', mealId: $this->mealA->id);

        $cart->assertCount('items', 1);
        $this->assertFalse($cart->get('hasSoldOutItems'));

        // 2. Kitchen toggles dish to 86'd
        $this->actingAs($this->chef);
        $toggleAction = new ToggleMealAvailabilityAction();
        $toggleAction->handle($this->mealA);

        $this->assertFalse($this->mealA->fresh()->is_available);

        // 3. Cashier attempts to proceed to checkout
        $this->actingAs($this->cashier);
        $cart->call('proceedToCheckout');

        // Must block checkout modal from opening
        $cart->assertNotDispatched('open-checkout');
        $cart->assertDispatched('pos-alert', function ($event, $params) {
            $payload = isset($params['type']) ? $params : ($params[0] ?? []);
            return ($payload['type'] ?? '') === 'error' && str_contains($payload['message'] ?? '', 'Sold Out');
        });

        $this->assertTrue($cart->get('hasSoldOutItems'));
    }

    public function test_checkout_modal_catches_mid_checkout_86d_toggle_and_prevents_payment_and_ticket_creation(): void
    {
        $this->actingAs($this->cashier);

        // 1. Cashier opens checkout modal while dish is available
        $modal = Livewire::actingAs($this->cashier)
            ->test(CheckoutModal::class)
            ->call('openWithData', [
                'items' => [
                    [
                        'meal_id' => $this->mealA->id,
                        'name' => $this->mealA->name,
                        'price' => (float) $this->mealA->price,
                        'quantity' => 1,
                    ],
                ],
                'order_type' => OrderType::DINE_IN->value,
                'table_number' => 'T-10',
                'subtotal' => 180.00,
                'total_amount' => 180.00,
            ]);

        $this->assertTrue($modal->get('isOpen'));

        // 2. Mid-checkout race: Kitchen chef toggles dish to 86'd while cashier has modal open!
        $this->actingAs($this->chef);
        (new ToggleMealAvailabilityAction())->handle($this->mealA);
        $this->assertFalse($this->mealA->fresh()->is_available);

        // 3. Cashier hits "Pay / Complete Order"
        $this->actingAs($this->cashier);
        $createOrderAction = app(CreateOrderAction::class);
        $processPaymentAction = app(ProcessPaymentAction::class);

        $modal->call('processCheckout', $createOrderAction, $processPaymentAction);

        // 4. Modal must catch the 86'd exception and expose the error message
        $this->assertNotNull($modal->get('errorMessage'));
        $this->assertStringContainsString("marked as 86'd / Sold Out", (string) $modal->get('errorMessage'));

        // Invariants: ZERO orders created, ZERO payments created, ZERO kitchen tickets created
        $this->assertEquals(0, Order::count());
        $this->assertEquals(0, Payment::count());
        $this->assertEquals(0, KitchenTicket::count());

        // 5. Recovery test: Kitchen marks dish available again, cashier re-submits payment -> succeeds!
        $this->actingAs($this->chef);
        (new ToggleMealAvailabilityAction())->handle($this->mealA);
        $this->assertTrue($this->mealA->fresh()->is_available);

        $this->actingAs($this->cashier);
        $modal->call('processCheckout', $createOrderAction, $processPaymentAction);

        $this->assertNull($modal->get('errorMessage'));
        $this->assertFalse($modal->get('isOpen'));
        $this->assertEquals(1, Order::count());
        $this->assertEquals(1, Payment::count());
        $this->assertEquals(1, KitchenTicket::count());
    }

    public function test_client_tampering_direct_checkout_with_sold_out_meal_is_blocked_at_write_boundary(): void
    {
        $this->actingAs($this->cashier);

        // Dish is 86'd
        $this->mealB->update(['is_available' => false]);

        $modal = Livewire::actingAs($this->cashier)->test(CheckoutModal::class);
        $createOrderAction = app(CreateOrderAction::class);
        $processPaymentAction = app(ProcessPaymentAction::class);

        // Simulating direct client call to processCheckoutFromClient
        $errorMessage = $modal->instance()->processCheckoutFromClient([
            'items' => [
                ['meal_id' => $this->mealB->id, 'quantity' => 1],
            ],
            'order_type' => OrderType::TAKEOUT->value,
            'payment_method' => PaymentMethod::CASH->value,
            'amount_tendered' => 250.00,
        ], $createOrderAction, $processPaymentAction);

        $this->assertNotNull($errorMessage);
        $this->assertStringContainsString("marked as 86'd / Sold Out", $errorMessage);
        $this->assertEquals(0, Order::count());
        $this->assertEquals(0, Payment::count());
    }

    // =========================================================================
    // SECTION 3: PESSIMISTIC LOCKING & MEAL STOCK DECREMENT INVARIANTS
    // =========================================================================

    public function test_pessimistic_stock_locking_prevents_over_allocation(): void
    {
        $this->actingAs($this->cashier);

        // Meal B has stock of 5
        $this->assertEquals(5, $this->mealB->fresh()->stock_quantity);

        $action = new CreateOrderAction();

        // Order 1 requests 4 portions -> succeeds, leaving 1 portion
        $order1 = $action->execute([
            'branch_id' => $this->branch->id,
            'cashier_id' => $this->cashier->id,
            'order_type' => OrderType::DINE_IN->value,
            'items' => [
                ['meal_id' => $this->mealB->id, 'quantity' => 4],
            ],
        ]);

        $this->assertNotNull($order1);
        $this->assertEquals(1, $this->mealB->fresh()->stock_quantity);

        // Order 2 concurrently requests 2 portions (only 1 available!)
        try {
            $action->execute([
                'branch_id' => $this->branch->id,
                'cashier_id' => $this->cashier->id,
                'order_type' => OrderType::DINE_IN->value,
                'items' => [
                    ['meal_id' => $this->mealB->id, 'quantity' => 2],
                ],
            ]);
            $this->fail('Expected InvalidArgumentException for insufficient portion stock.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString("Insufficient portions left for '{$this->mealB->name}'. Only 1 available.", $e->getMessage());
        }

        // Inventory invariant: Stock remains strictly at 1, exactly 1 order exists
        $this->assertEquals(1, $this->mealB->fresh()->stock_quantity);
        $this->assertEquals(1, Order::count());
    }

    public function test_pessimistic_stock_locking_to_zero_auto_disables_availability(): void
    {
        $this->actingAs($this->cashier);

        // Meal B has stock of 5
        $action = new CreateOrderAction();

        // Exact match: Order requests exactly 5 portions
        $order = $action->execute([
            'branch_id' => $this->branch->id,
            'cashier_id' => $this->cashier->id,
            'order_type' => OrderType::DINE_IN->value,
            'items' => [
                ['meal_id' => $this->mealB->id, 'quantity' => 5],
            ],
        ]);

        $this->assertNotNull($order);

        // Invariant: Stock must be 0 and is_available must be automatically set to false!
        $freshMeal = $this->mealB->fresh();
        $this->assertEquals(0, $freshMeal->stock_quantity);
        $this->assertFalse($freshMeal->is_available);
        $this->assertTrue($freshMeal->isSoldOut());

        // Subsequent order must be rejected immediately
        try {
            $action->execute([
                'branch_id' => $this->branch->id,
                'cashier_id' => $this->cashier->id,
                'order_type' => OrderType::DINE_IN->value,
                'items' => [
                    ['meal_id' => $this->mealB->id, 'quantity' => 1],
                ],
            ]);
            $this->fail('Expected order on 0-stock meal to be rejected.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString("marked as 86'd / Sold Out", $e->getMessage());
        }

        $this->assertEquals(0, $this->mealB->fresh()->stock_quantity);
        $this->assertEquals(1, Order::count());
    }

    public function test_multi_item_order_rolls_back_entirely_if_any_single_meal_lacks_stock(): void
    {
        $this->actingAs($this->cashier);

        // Meal A has stock 10. Meal B has stock 5.
        $this->assertEquals(10, $this->mealA->fresh()->stock_quantity);
        $this->assertEquals(5, $this->mealB->fresh()->stock_quantity);

        $action = new CreateOrderAction();

        // Order requests 4 portions of Meal A (valid), but 10 portions of Meal B (invalid, only 5 in stock)
        try {
            $action->execute([
                'branch_id' => $this->branch->id,
                'cashier_id' => $this->cashier->id,
                'order_type' => OrderType::DINE_IN->value,
                'items' => [
                    ['meal_id' => $this->mealA->id, 'quantity' => 4],
                    ['meal_id' => $this->mealB->id, 'quantity' => 10],
                ],
            ]);
            $this->fail('Expected InvalidArgumentException due to insufficient Meal B stock.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString("Insufficient portions left for '{$this->mealB->name}'", $e->getMessage());
        }

        // Atomicity Invariant: Meal A portion decrement MUST be rolled back!
        $this->assertEquals(10, $this->mealA->fresh()->stock_quantity, 'Meal A stock must remain 10 (rolled back).');
        $this->assertEquals(5, $this->mealB->fresh()->stock_quantity, 'Meal B stock must remain 5.');
        $this->assertEquals(0, Order::count());
        $this->assertEquals(0, OrderItem::count());
    }

    public function test_intra_order_duplicate_meal_aggregates_stock_decrement_properly(): void
    {
        $this->actingAs($this->cashier);

        // Meal B has stock 5. Order contains Meal B twice: qty 3 and qty 3 (sum = 6, exceeds 5)
        $action = new CreateOrderAction();

        try {
            $action->execute([
                'branch_id' => $this->branch->id,
                'cashier_id' => $this->cashier->id,
                'order_type' => OrderType::DINE_IN->value,
                'items' => [
                    ['meal_id' => $this->mealB->id, 'quantity' => 3, 'special_instructions' => 'Extra spicy'],
                    ['meal_id' => $this->mealB->id, 'quantity' => 3, 'special_instructions' => 'Mild sauce'],
                ],
            ]);
            $this->fail('Expected InvalidArgumentException for intra-order portion over-allocation.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString("Insufficient portions left for '{$this->mealB->name}'", $e->getMessage());
        }

        // Entire transaction rolls back: stock must remain 5
        $this->assertEquals(5, $this->mealB->fresh()->stock_quantity);
        $this->assertEquals(0, Order::count());
    }

    public function test_soft_deleted_meal_mid_checkout_is_rejected_and_rolled_back(): void
    {
        $this->actingAs($this->cashier);

        // Meal A exists with stock 10
        $action = new CreateOrderAction();

        // Chef soft-deletes meal A
        $this->mealA->delete();
        $this->assertSoftDeleted('meals', ['id' => $this->mealA->id]);

        try {
            $action->execute([
                'branch_id' => $this->branch->id,
                'cashier_id' => $this->cashier->id,
                'order_type' => OrderType::DINE_IN->value,
                'items' => [
                    ['meal_id' => $this->mealA->id, 'quantity' => 1],
                ],
            ]);
            $this->fail('Expected InvalidArgumentException for soft-deleted meal.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString("Meal ID {$this->mealA->id} not found", $e->getMessage());
        }

        $this->assertEquals(0, Order::count());
    }

    public function test_unauthorized_user_cannot_toggle_meal_availability(): void
    {
        // Cashier does not have menu.manage permission
        $this->actingAs($this->cashier);

        $action = new ToggleMealAvailabilityAction();

        try {
            $action->handle($this->mealA);
            $this->fail('Expected 403 HttpException when cashier attempts to toggle meal availability.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertEquals(403, $e->getStatusCode());
            $this->assertStringContainsString('Unauthorized. Only Kitchen and Managers can toggle dish availability.', $e->getMessage());
        }

        // Availability must remain unchanged
        $this->assertTrue($this->mealA->fresh()->is_available);
    }

    public function test_cross_branch_chef_cannot_toggle_another_branch_dish(): void
    {
        $branch2 = Branch::firstOrCreate(['code' => 'B02'], ['name' => 'Cebu Branch', 'is_active' => true]);
        $chefB2 = User::firstOrCreate(['emp_id' => 'B02-CHF-ADV2'], [
            'name' => 'Cebu Chef',
            'email' => 'cebu_chf_adv2@easypos.test',
            'password' => Hash::make('password'),
            'branch_id' => $branch2->id,
            'is_active' => true,
        ]);
        $chefB2->assignRole(StaffRole::CHEF->value);

        // Chef B2 attempts to toggle Branch 1's mealA
        $this->actingAs($chefB2);
        $action = new ToggleMealAvailabilityAction();

        try {
            $action->handle($this->mealA);
            $this->fail('Expected 403 HttpException when cross-branch chef toggles dish from another branch.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertEquals(403, $e->getStatusCode());
            $this->assertStringContainsString('You cannot toggle availability for a dish belonging to another branch.', $e->getMessage());
        }

        // Branch 1 meal availability remains unchanged
        $this->assertTrue($this->mealA->fresh()->is_available);
    }

    public function test_tampered_invalid_quantities_are_strictly_rejected(): void
    {
        $this->actingAs($this->cashier);
        $action = new CreateOrderAction();

        $invalidQuantities = [0, -1, -50, 1001, '2.5', 'abc', null];

        foreach ($invalidQuantities as $qty) {
            try {
                $action->execute([
                    'branch_id' => $this->branch->id,
                    'cashier_id' => $this->cashier->id,
                    'order_type' => OrderType::DINE_IN->value,
                    'items' => [
                        ['meal_id' => $this->mealA->id, 'quantity' => $qty],
                    ],
                ]);
                $this->fail("Expected InvalidArgumentException for invalid quantity: " . var_export($qty, true));
            } catch (InvalidArgumentException $e) {
                $this->assertTrue(
                    str_contains($e->getMessage(), 'positive integer') || str_contains($e->getMessage(), 'maximum allowed limit'),
                    "Unexpected error message: {$e->getMessage()}"
                );
            }
        }

        $this->assertEquals(0, Order::count());
    }

    public function test_concurrent_depletion_exact_stock_invariant_under_rapid_fire_orders(): void
    {
        $this->actingAs($this->cashier);

        // Meal with exactly 7 portions in stock
        $trackedMeal = Meal::create([
            'name' => 'Wagyu Cubes',
            'menu_code' => 'B01-WAGYU',
            'category_id' => $this->category->id,
            'branch_id' => $this->branch->id,
            'price' => 350.00,
            'track_stock' => true,
            'stock_quantity' => 7,
            'is_available' => true,
        ]);

        $action = new CreateOrderAction();

        // 7 individual orders of 1 portion each: all 7 must succeed
        for ($i = 1; $i <= 7; $i++) {
            $order = $action->execute([
                'branch_id' => $this->branch->id,
                'cashier_id' => $this->cashier->id,
                'order_type' => OrderType::DINE_IN->value,
                'items' => [
                    ['meal_id' => $trackedMeal->id, 'quantity' => 1],
                ],
            ]);
            $this->assertNotNull($order);
            $this->assertEquals(7 - $i, $trackedMeal->fresh()->stock_quantity);
        }

        // Stock is now 0 and meal is automatically marked unavailable
        $fresh = $trackedMeal->fresh();
        $this->assertEquals(0, $fresh->stock_quantity);
        $this->assertFalse($fresh->is_available);

        // 8th order must fail
        try {
            $action->execute([
                'branch_id' => $this->branch->id,
                'cashier_id' => $this->cashier->id,
                'order_type' => OrderType::DINE_IN->value,
                'items' => [
                    ['meal_id' => $trackedMeal->id, 'quantity' => 1],
                ],
            ]);
            $this->fail('Expected 8th order to fail on exhausted stock.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString("marked as 86'd / Sold Out", $e->getMessage());
        }

        // Final invariant: Exactly 7 orders created, stock remains 0, no negative inventory
        $this->assertEquals(7, Order::count());
        $this->assertEquals(0, $trackedMeal->fresh()->stock_quantity);
    }
}

