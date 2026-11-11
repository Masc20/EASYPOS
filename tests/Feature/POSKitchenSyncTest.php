<?php

namespace Tests\Feature;

use App\Domains\Identity\Models\User;
use App\Domains\Kitchen\Livewire\KitchenDisplay;
use App\Domains\Menu\Actions\ToggleMealAvailabilityAction;
use App\Domains\Menu\Events\MealAvailabilityToggled;
use App\Domains\Menu\Models\Meal;
use App\Domains\Menu\Models\MealCategory;
use App\Domains\PointOfSale\Actions\CreateOrderAction;
use App\Domains\PointOfSale\Actions\ProcessPaymentAction;
use App\Domains\PointOfSale\Livewire\Checkout\CheckoutCart;
use App\Domains\PointOfSale\Livewire\MealCatalog;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

class POSKitchenSyncTest extends TestCase
{
    use RefreshDatabase;

    protected User $chef;
    protected User $cashier;
    protected User $cook;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(MenuSeeder::class);

        $this->chef = User::where('emp_id', 'B01-CHF-001')->firstOrFail();
        $this->cashier = User::where('emp_id', 'B01-CSH-001')->firstOrFail();
        $this->cook = User::where('emp_id', 'B01-COK-001')->firstOrFail();
    }

    public function test_menu_version_increments_in_cache_when_dish_availability_is_toggled(): void
    {
        $meal = Meal::where('menu_code', 'M-01')->firstOrFail();
        $branchId = $meal->branch_id ?? 1;

        Cache::forget("branch_{$branchId}_menu_version");
        Cache::forget('branch_global_menu_version');

        $before = now()->timestamp;
        $this->actingAs($this->chef);
        $action = new ToggleMealAvailabilityAction();
        $action->handle($meal);
        $after = now()->timestamp;

        $version = Cache::get("branch_{$branchId}_menu_version");
        $this->assertNotNull($version);
        $this->assertGreaterThan(0, $version);
        $this->assertGreaterThanOrEqual($before, $version);
        $this->assertLessThanOrEqual($after, $version);

        // Test global dish availability toggle (branch_id = null)
        $globalMeal = Meal::create([
            'branch_id' => null,
            'category_id' => $meal->category_id,
            'name' => 'Global Specialty Soup',
            'price' => 199.00,
            'is_available' => true,
            'is_active' => true,
        ]);

        $beforeGlobal = now()->timestamp;
        $action->handle($globalMeal);
        $afterGlobal = now()->timestamp;

        $globalVersion = Cache::get('branch_global_menu_version');
        $this->assertNotNull($globalVersion);
        $this->assertGreaterThanOrEqual($beforeGlobal, $globalVersion);
        $this->assertLessThanOrEqual($afterGlobal, $globalVersion);
    }

    public function test_meal_availability_toggled_implements_should_broadcast_with_channel_and_payload(): void
    {
        $meal = Meal::where('menu_code', 'M-01')->firstOrFail();
        $event = new MealAvailabilityToggled($meal);

        // Implements ShouldBroadcast contract
        $this->assertInstanceOf(ShouldBroadcast::class, $event);

        // Private channel targeting branch
        $channels = $event->broadcastOn();
        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $expectedChannel = 'private-branch.'.($meal->branch_id ?? 'global');
        $this->assertEquals($expectedChannel, $channels[0]->name);

        // Payload contains meal_id, is_available, branch_id, name
        $payload = $event->broadcastWith();
        $this->assertIsArray($payload);
        $this->assertEquals($meal->id, $payload['meal_id']);
        $this->assertEquals((bool) $meal->is_available, $payload['is_available']);
        $this->assertEquals($meal->branch_id, $payload['branch_id']);
        $this->assertEquals($meal->name, $payload['name']);

        // Test global meal channel format
        $globalMeal = Meal::create([
            'branch_id' => null,
            'category_id' => $meal->category_id,
            'name' => 'HQ Secret Sauce',
            'price' => 50.00,
            'is_available' => true,
            'is_active' => true,
        ]);

        $globalEvent = new MealAvailabilityToggled($globalMeal);
        $globalChannels = $globalEvent->broadcastOn();
        $this->assertEquals('private-branch.global', $globalChannels[0]->name);
    }

    public function test_meal_catalog_sync_availability_skips_render_when_version_unchanged_and_renders_when_changed(): void
    {
        $branchId = $this->cashier->branch_id ?? 1;
        Cache::forever("branch_{$branchId}_menu_version", 1000);
        Cache::forever('branch_global_menu_version', 500);

        $testable = Livewire::actingAs($this->cashier)->test(MealCatalog::class);

        // 1. Initial poll without changes -> skipRender is active, so effects['html'] is NOT generated
        $testable->call('syncAvailability');
        $this->assertFalse(isset($testable->effects['html']), 'Expected html effect to be skipped when version unchanged.');

        // 2. Menu version changes in cache (e.g. Chef toggles item)
        Cache::forever("branch_{$branchId}_menu_version", 2000);

        // 3. Next poll detects change -> does not skip render, updates version, dispatches sync event
        $testable->call('syncAvailability');
        $this->assertTrue(isset($testable->effects['html']), 'Expected html effect to be rendered when version changes.');
        $testable->assertDispatched('meal-availability-synced');

        // 4. Subsequent poll without changes -> skips render again
        $testable->call('syncAvailability');
        $this->assertFalse(isset($testable->effects['html']), 'Expected html effect to be skipped again when version unchanged.');
    }

    public function test_kitchen_display_poll_updates_skips_render_when_ticket_hash_unchanged_and_rerenders_with_chime_when_tickets_change(): void
    {
        $testable = Livewire::actingAs($this->cook)->test(KitchenDisplay::class);

        // 1. First poll with no ticket changes -> skipRender is active
        $testable->call('pollUpdates');
        $this->assertFalse(isset($testable->effects['html']), 'Expected html effect to be skipped when tickets unchanged.');

        // 2. Incoming paid order creates new kitchen ticket
        $meal = Meal::firstOrFail();
        $createOrderAction = app(CreateOrderAction::class);
        $order = $createOrderAction->execute([
            'cashier_id' => $this->cashier->id,
            'branch_id' => $this->cook->branch_id ?? 1,
            'order_type' => 'dine_in',
            'table_number' => 'Sync-1',
            'items' => [
                [
                    'meal_id' => $meal->id,
                    'quantity' => 1,
                    'unit_price' => (float) $meal->price,
                ],
            ],
        ]);

        $processPaymentAction = app(ProcessPaymentAction::class);
        $processPaymentAction->execute($order, [
            'payment_method' => 'cash',
            'amount_tendered' => (float) $order->total_amount + 50.00,
            'cashier_id' => $this->cashier->id,
        ]);

        // 3. Poll updates detects new ticket: skipRender is not active, chime is dispatched
        $testable->call('pollUpdates');
        $this->assertTrue(isset($testable->effects['html']), 'Expected html effect to be rendered when tickets change.');
        $testable->assertDispatched('play-kitchen-chime');

        // 4. Subsequent poll without ticket changes -> skips render again
        $testable->call('pollUpdates');
        $this->assertFalse(isset($testable->effects['html']), 'Expected html effect to be skipped again when tickets unchanged.');
    }

    public function test_cart_detects_newly_sold_out_item_and_blocks_proceed_to_checkout(): void
    {
        $meal = Meal::where('menu_code', 'M-01')->firstOrFail();
        $this->assertTrue($meal->is_available);

        $testable = Livewire::actingAs($this->cashier)
            ->test(CheckoutCart::class)
            ->dispatch('add-to-cart', mealId: $meal->id);

        $testable->assertCount('items', 1);

        // Kitchen marks dish as 86'd / sold out while in cashier cart
        $meal->update(['is_available' => false]);

        // Attempting to proceed to checkout must be blocked
        $testable->call('proceedToCheckout');

        $testable->assertNotDispatched('open-checkout');
        $testable->assertDispatched('pos-alert', function ($event, $params) {
            $payload = isset($params['type']) ? $params : ($params[0] ?? []);
            return ($payload['type'] ?? '') === 'error' && str_contains($payload['message'] ?? '', 'Sold Out');
        });

        $items = $testable->get('items');
        $this->assertTrue($items[0]['is_sold_out']);

        // Restoring availability allows proceeding to checkout modal
        $meal->update(['is_available' => true]);
        $testable->call('proceedToCheckout');
        $testable->assertDispatched('open-checkout');
    }

    public function test_cart_verifies_availability_when_meal_availability_synced_event_is_received(): void
    {
        $meal = Meal::where('menu_code', 'M-01')->firstOrFail();

        $testable = Livewire::actingAs($this->cashier)
            ->test(CheckoutCart::class)
            ->dispatch('add-to-cart', mealId: $meal->id);

        $testable->assertCount('items', 1);

        // Meal is marked sold out
        $meal->update(['is_available' => false]);

        // Background availability sync event triggers cart verification
        $testable->dispatch('meal-availability-synced');

        $testable->assertDispatched('pos-alert');
        $items = $testable->get('items');
        $this->assertTrue($items[0]['is_sold_out']);
    }

    public function test_branch_isolation_in_menu_version_sync(): void
    {
        $mealB2 = Meal::where('branch_id', 2)->first();
        if (! $mealB2) {
            $cat = MealCategory::first();
            $mealB2 = Meal::create([
                'branch_id' => 2,
                'category_id' => $cat->id,
                'name' => 'Branch 2 Exclusive Steak',
                'price' => 500.00,
                'is_available' => true,
                'is_active' => true,
            ]);
        }

        // Set branch 1 version
        Cache::forever('branch_1_menu_version', 100);
        Cache::forever('branch_2_menu_version', 100);
        Cache::forget('branch_global_menu_version');

        // Mount Branch 1 POS catalog
        $testableB1 = Livewire::actingAs($this->cashier)->test(MealCatalog::class);
        $componentB1 = $testableB1->instance();

        // Chef toggles Branch 2 meal
        $chefB2 = User::where('emp_id', 'B02-CHF-001')->first();
        if ($chefB2) {
            $this->actingAs($chefB2);
            (new ToggleMealAvailabilityAction())->handle($mealB2);
        } else {
            Cache::forever('branch_2_menu_version', 200);
        }

        // Switch back to Branch 1 Cashier
        $this->actingAs($this->cashier);

        // Branch 1 menu version in cache must NOT have changed
        $this->assertEquals(100, Cache::get('branch_1_menu_version'));

        // Branch 1 catalog should continue skipping render
        $testableB1->call('syncAvailability');
        $this->assertFalse(isset($testableB1->effects['html']), 'Expected branch 1 catalog to skip render when branch 2 menu toggled.');
    }
}
