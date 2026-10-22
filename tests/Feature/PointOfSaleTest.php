<?php

namespace Tests\Feature;

use App\Domains\Identity\Models\User;
use App\Domains\Kitchen\Models\KitchenTicket;
use App\Domains\Menu\Actions\ToggleMealAvailabilityAction;
use App\Domains\Menu\Events\MealAvailabilityToggled;
use App\Domains\Menu\Models\Meal;
use App\Domains\Menu\Models\MealCategory;
use App\Domains\PointOfSale\Actions\CreateOrderAction;
use App\Domains\PointOfSale\Actions\ProcessPaymentAction;
use App\Domains\PointOfSale\Events\KitchenTicketDispatched;
use App\Domains\PointOfSale\Events\OrderCreated;
use App\Domains\PointOfSale\Events\PaymentProcessed;
use App\Domains\PointOfSale\Livewire\Checkout\CheckoutCart;
use App\Domains\PointOfSale\Livewire\Checkout\CheckoutModal;
use App\Domains\PointOfSale\Livewire\ProductSearch;
use App\Domains\PointOfSale\Models\Order;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PointOfSaleTest extends TestCase
{
    use RefreshDatabase;

    protected User $cashier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(MenuSeeder::class);

        $this->cashier = User::where('emp_id', 'B01-CSH-001')->firstOrFail();
    }

    public function test_cashier_can_access_pos_screen(): void
    {
        $response = $this->actingAs($this->cashier)->get('/pos');

        $response->assertStatus(200);
        $response->assertSee('Point of Sale Terminal');
        $response->assertSee('Terminal Active');
        $response->assertSee($this->cashier->name);
    }

    public function test_meal_catalog_renders_categories_and_meals(): void
    {
        Livewire::actingAs($this->cashier)
            ->test(\App\Domains\PointOfSale\Livewire\MealCatalog::class)
            ->assertSee('All Dishes')
            ->assertSee('Truffle Mushroom Risotto')
            ->assertSee('M-01')
            ->assertSee('₱420.00');
    }

    public function test_product_search_dispatches_catalog_search_event(): void
    {
        Livewire::actingAs($this->cashier)
            ->test(ProductSearch::class)
            ->set('query', 'Ribeye')
            ->assertDispatched('catalog-search', query: 'Ribeye');
    }

    public function test_product_search_scanner_adds_exact_code_directly_to_cart(): void
    {
        Livewire::actingAs($this->cashier)
            ->test(ProductSearch::class)
            ->set('query', 'M-01')
            ->call('scanOrSubmit')
            ->assertDispatched('add-to-cart');
    }

    public function test_meal_catalog_filters_by_category(): void
    {
        $category = MealCategory::where('slug', 'starters')->first();

        Livewire::actingAs($this->cashier)
            ->test(\App\Domains\PointOfSale\Livewire\MealCatalog::class)
            ->call('selectCategory', $category->id)
            ->assertSet('selectedCategoryId', $category->id);
    }

    public function test_cart_adds_items_and_calculates_subtotal(): void
    {
        $meal = Meal::where('name', 'Truffle Mushroom Risotto')->first();

        Livewire::actingAs($this->cashier)
            ->test(CheckoutCart::class)
            ->dispatch('add-to-cart', mealId: $meal->id)
            ->assertCount('items', 1)
            ->assertSee('₱420.00')
            ->assertSee('Active Order')
            ->call('incrementQuantity', 0)
            ->assertSee('₱840.00');
    }

    public function test_cart_calculates_senior_pwd_discount(): void
    {
        $meal = Meal::where('name', 'Truffle Mushroom Risotto')->first(); // ₱420.00

        Livewire::actingAs($this->cashier)
            ->test(CheckoutCart::class)
            ->dispatch('add-to-cart', mealId: $meal->id)
            ->set('discountType', 'senior_pwd')
            ->assertSee('-₱84.00') // 20% of 420 is 84
            ->assertSee('₱336.00'); // 420 - 84 = 336
    }

    public function test_create_order_action_persists_order_and_items(): void
    {
        Event::fake([OrderCreated::class]);

        $meal = Meal::where('name', 'Truffle Mushroom Risotto')->first();
        $action = new CreateOrderAction();

        $order = $action->execute([
            'branch_id' => $this->cashier->branch_id,
            'cashier_id' => $this->cashier->id,
            'order_type' => 'dine_in',
            'table_number' => 'T-05',
            'customer_name' => 'John Doe',
            'discount_amount' => 0.00,
            'items' => [
                [
                    'meal_id' => $meal->id,
                    'quantity' => 2,
                    'special_instructions' => 'Extra creamy',
                ],
            ],
        ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'branch_id' => $this->cashier->branch_id,
            'cashier_id' => $this->cashier->id,
            'order_type' => 'dine_in',
            'table_number' => 'T-05',
            'subtotal' => 840.00,
            'total_amount' => 840.00,
            'status' => 'pending',
            'payment_status' => 'unpaid',
        ]);

        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'meal_id' => $meal->id,
            'quantity' => 2,
            'unit_price' => 420.00,
            'subtotal' => 840.00,
            'special_instructions' => 'Extra creamy',
        ]);

        Event::assertDispatched(OrderCreated::class);
    }

    public function test_process_payment_action_creates_payment_and_kitchen_ticket(): void
    {
        Event::fake([PaymentProcessed::class, KitchenTicketDispatched::class]);

        $meal = Meal::where('name', 'Truffle Mushroom Risotto')->first();
        $createAction = new CreateOrderAction();
        $paymentAction = new ProcessPaymentAction();

        $order = $createAction->execute([
            'branch_id' => $this->cashier->branch_id,
            'cashier_id' => $this->cashier->id,
            'order_type' => 'dine_in',
            'table_number' => 'T-02',
            'items' => [
                ['meal_id' => $meal->id, 'quantity' => 1],
            ],
        ]);

        $result = $paymentAction->execute($order, [
            'cashier_id' => $this->cashier->id,
            'payment_method' => 'cash',
            'amount_tendered' => 500.00,
        ]);

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'payment_method' => 'cash',
            'amount' => 420.00,
            'amount_tendered' => 500.00,
            'change_given' => 80.00,
            'status' => 'successful',
        ]);

        $this->assertEquals('paid', $order->fresh()->payment_status);
        $this->assertEquals('in_kitchen', $order->fresh()->status);

        $this->assertDatabaseHas('kitchen_tickets', [
            'order_id' => $order->id,
            'branch_id' => $this->cashier->branch_id,
            'status' => 'new',
        ]);

        Event::assertDispatched(PaymentProcessed::class);
        Event::assertDispatched(KitchenTicketDispatched::class);
    }

    public function test_checkout_modal_completes_sale_and_triggers_receipt(): void
    {
        $meal = Meal::where('name', 'Classic Chicken Parmigiana')->first(); // ₱340.00

        Livewire::actingAs($this->cashier)
            ->test(CheckoutModal::class)
            ->dispatch('open-checkout', [
                'items' => [
                    [
                        'meal_id' => $meal->id,
                        'name' => $meal->name,
                        'menu_code' => $meal->menu_code,
                        'price' => (float) $meal->price,
                        'quantity' => 1,
                    ],
                ],
                'order_type' => 'dine_in',
                'table_number' => 'T-01',
                'customer_name' => 'Alice',
                'subtotal' => 340.00,
                'discount_amount' => 0.00,
                'total_amount' => 340.00,
            ])
            ->assertSet('isOpen', true)
            ->set('amountTendered', '500.00')
            ->call('processCheckout')
            ->assertSet('isOpen', false)
            ->assertDispatched('cart-reset')
            ->assertDispatched('show-receipt');
    }

    public function test_meal_is_sold_out_helper_and_scopes(): void
    {
        $category = MealCategory::first();

        // 1. Available meal without stock tracking
        $meal1 = Meal::create([
            'category_id' => $category->id,
            'name' => 'Available Unlimited Dish',
            'price' => 100.00,
            'is_available' => true,
            'track_stock' => false,
            'is_active' => true,
        ]);
        $this->assertFalse($meal1->isSoldOut());

        // 2. Available meal with stock > 0
        $meal2 = Meal::create([
            'category_id' => $category->id,
            'name' => 'Stocked Dish',
            'price' => 150.00,
            'is_available' => true,
            'track_stock' => true,
            'stock_quantity' => 10,
            'is_active' => true,
        ]);
        $this->assertFalse($meal2->isSoldOut());

        // 3. Unavailable meal (86'd by chef)
        $meal3 = Meal::create([
            'category_id' => $category->id,
            'name' => 'Chef 86d Dish',
            'price' => 200.00,
            'is_available' => false,
            'track_stock' => false,
            'is_active' => true,
        ]);
        $this->assertTrue($meal3->isSoldOut());

        // 4. Meal with 0 daily portions
        $meal4 = Meal::create([
            'category_id' => $category->id,
            'name' => 'Depleted Portion Dish',
            'price' => 250.00,
            'is_available' => true,
            'track_stock' => true,
            'stock_quantity' => 0,
            'is_active' => true,
        ]);
        $this->assertTrue($meal4->isSoldOut());

        // Scopes test
        $soldOutIds = Meal::soldOut()->pluck('id')->all();
        $this->assertContains($meal3->id, $soldOutIds);
        $this->assertContains($meal4->id, $soldOutIds);
        $this->assertNotContains($meal1->id, $soldOutIds);
        $this->assertNotContains($meal2->id, $soldOutIds);

        $availableIds = Meal::available()->pluck('id')->all();
        $this->assertContains($meal1->id, $availableIds);
        $this->assertContains($meal2->id, $availableIds);
        $this->assertNotContains($meal3->id, $availableIds);
        $this->assertNotContains($meal4->id, $availableIds);
    }

    public function test_meal_catalog_shows_86d_badge_and_sold_out_count(): void
    {
        // Mark one meal as 86'd
        $risotto = Meal::where('menu_code', 'M-01')->firstOrFail();
        $risotto->update(['is_available' => false]);

        // Mark another meal as 0 portions
        $ribeye = Meal::where('menu_code', 'M-02')->firstOrFail();
        $ribeye->update(['track_stock' => true, 'stock_quantity' => 0]);

        Livewire::actingAs($this->cashier)
            ->test(\App\Domains\PointOfSale\Livewire\MealCatalog::class)
            ->assertSeeHtml("86'd / Sold Out")
            ->assertSeeHtml("Sold Out (86'd)")
            ->assertSee("Unavailable")
            ->assertViewHas('soldOutCount', 2);
    }

    public function test_cart_rejects_adding_sold_out_or_unavailable_meal(): void
    {
        $risotto = Meal::where('menu_code', 'M-01')->firstOrFail();
        $risotto->update(['is_available' => false]);

        Livewire::actingAs($this->cashier)
            ->test(CheckoutCart::class)
            ->dispatch('add-to-cart', mealId: $risotto->id)
            ->assertDispatched('pos-alert', function ($event, $params) {
                $payload = isset($params['type']) ? $params : ($params[0] ?? []);
                return ($payload['type'] ?? '') === 'warning' && str_contains($payload['message'] ?? '', "86'd (Sold Out)");
            })
            ->assertCount('items', 0);
    }

    public function test_scanner_rejects_86d_meal_and_alerts_cashier(): void
    {
        $risotto = Meal::where('menu_code', 'M-01')->firstOrFail();
        $risotto->update(['is_available' => false]);

        Livewire::actingAs($this->cashier)
            ->test(ProductSearch::class)
            ->set('query', 'M-01')
            ->call('scanOrSubmit')
            ->assertDispatched('pos-alert', function ($event, $params) {
                $payload = isset($params['type']) ? $params : ($params[0] ?? []);
                return ($payload['type'] ?? '') === 'warning' && str_contains($payload['message'] ?? '', "86'd (Sold Out)");
            })
            ->assertNotDispatched('add-to-cart');
    }

    public function test_create_order_action_throws_exception_for_86d_meal(): void
    {
        $risotto = Meal::where('menu_code', 'M-01')->firstOrFail();
        $risotto->update(['is_available' => false]);

        $action = new CreateOrderAction();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Meal '{$risotto->name}' is marked as 86'd / Sold Out.");

        $action->execute([
            'branch_id' => $this->cashier->branch_id,
            'cashier_id' => $this->cashier->id,
            'order_type' => 'dine_in',
            'items' => [
                ['meal_id' => $risotto->id, 'quantity' => 1],
            ],
        ]);
    }

    public function test_cashier_cannot_toggle_meal_availability_action(): void
    {
        $risotto = Meal::where('menu_code', 'M-01')->firstOrFail();
        $this->assertTrue($risotto->is_available);

        $this->actingAs($this->cashier);

        try {
            (new ToggleMealAvailabilityAction())->handle($risotto);
            $this->fail('Expected 403 HttpException was not thrown.');
        } catch (HttpException $e) {
            $this->assertEquals(403, $e->getStatusCode());
            $this->assertStringContainsString('Unauthorized', $e->getMessage());
        }

        $this->assertTrue($risotto->fresh()->is_available);
    }

    public function test_authorized_user_can_toggle_meal_availability(): void
    {
        Event::fake([MealAvailabilityToggled::class]);

        $chef = User::where('emp_id', 'B01-CHF-001')->firstOrFail();
        $risotto = Meal::where('menu_code', 'M-01')->firstOrFail();
        $this->assertTrue($risotto->is_available);

        $this->actingAs($chef);

        $action = new ToggleMealAvailabilityAction();
        $result = $action->handle($risotto);

        $this->assertFalse($result->is_available);
        $this->assertFalse($risotto->fresh()->is_available);
        Event::assertDispatched(MealAvailabilityToggled::class);

        // Toggle back to available
        $result2 = $action->handle($risotto);
        $this->assertTrue($result2->is_available);
        $this->assertTrue($risotto->fresh()->is_available);
    }
}
