<?php

namespace Tests\Feature\E2E;

use App\Domains\Audit\Models\ActivityLog;
use App\Domains\Identity\Livewire\Auth\Login;
use App\Domains\Kitchen\Livewire\KitchenDisplay;
use App\Domains\Kitchen\Models\KitchenTicket;
use App\Domains\Menu\Actions\ToggleMealAvailabilityAction;
use App\Domains\Menu\Actions\UpdateBranchPortionsAction;
use App\Domains\Menu\Models\Meal;
use App\Domains\PointOfSale\Livewire\Checkout\CheckoutModal;
use App\Domains\PointOfSale\Livewire\Checkout\ManagerPinModal;
use App\Domains\PointOfSale\Livewire\MealCatalog;
use App\Domains\PointOfSale\Livewire\OrderProgress;
use App\Domains\PointOfSale\Models\Order;
use App\Domains\PointOfSale\Models\Payment;
use App\Domains\PointOfSale\Services\SalesAggregationService;
use App\Support\Constants\SystemConstants;
use App\Support\Enums\KitchenTicketStatus;
use App\Support\Enums\OrderStatus;
use App\Support\Enums\OrderType;
use App\Support\Enums\PaymentMethod;
use App\Support\Enums\PaymentStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Psr\Log\LoggerInterface;
use Tests\Feature\E2E\Concerns\E2ETestHelpers;
use Tests\TestCase;

class Tier3WorkflowAndHardeningE2ETest extends TestCase
{
    use E2ETestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedE2EActors();
    }

    public function test_tier3_full_floor_pos_to_kds_to_order_progress_to_reports_and_csv_with_cross_branch_isolation(): void
    {
        ['meal' => $b1Dish] = $this->createBranchCategoryAndMeal($this->branch1, [
            'name' => 'B1 Signature Tapsilog',
            'price' => 200.00,
            'track_stock' => true,
            'stock_quantity' => 10,
        ]);
        ['meal' => $globalSide] = $this->createBranchCategoryAndMeal(null, [
            'name' => 'Global Garlic Rice',
            'price' => 50.00,
        ]);
        ['meal' => $b2Dish] = $this->createBranchCategoryAndMeal($this->branch2, [
            'name' => 'B2 Cebu Lechon Belly',
            'price' => 450.00,
        ]);

        // 1. Floor PIN login for Branch 1 Cashier
        Livewire::test(Login::class)
            ->set('emp_id', $this->cashier1->emp_id)
            ->set('pin', '1234')
            ->call('login')
            ->assertRedirect('/pos');

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $this->cashier1->id,
            'branch_id' => $this->branch1->id,
            'category' => 'attendance',
            'event' => 'clock_in',
            'status' => 'success',
        ]);

        $this->actingAs($this->cashier1)->get('/pos')->assertStatus(200);

        // 2. Cashier 1 checks out 2x B1 Signature Tapsilog + 1x Global Garlic Rice = 450.00 (tampered client total ignored)
        Livewire::actingAs($this->cashier1)
            ->test(CheckoutModal::class)
            ->call('openWithData', [
                'items' => [
                    ['meal_id' => $b1Dish->id, 'quantity' => 2, 'special_instructions' => 'Sunny side up'],
                    ['meal_id' => $globalSide->id, 'quantity' => 1, 'special_instructions' => 'Extra toasted'],
                ],
                'order_type' => OrderType::DINE_IN->value,
                'table_number' => '7',
                'customer_name' => 'Maria Clara',
                'subtotal' => 1.00,
                'total_amount' => 1.00,
            ])
            ->set('amountTendered', '500.00')
            ->call('processCheckout')
            ->assertSet('errorMessage', null)
            ->assertDispatched('cart-reset')
            ->assertDispatched('show-receipt');

        $b1Order = Order::where('branch_id', $this->branch1->id)->firstOrFail();
        $this->assertSame(450.00, (float) $b1Order->subtotal);
        $this->assertSame(450.00, (float) $b1Order->total_amount);
        $this->assertSame(OrderStatus::IN_KITCHEN->value, $b1Order->status);
        $this->assertSame(PaymentStatus::PAID->value, $b1Order->payment_status);

        $b1Payment = Payment::where('order_id', $b1Order->id)->firstOrFail();
        $this->assertSame(50.00, (float) $b1Payment->change_given);
        $this->assertSame(8, (int) $b1Dish->fresh()->stock_quantity);

        $b1Ticket = KitchenTicket::where('order_id', $b1Order->id)->with('items')->firstOrFail();
        $this->assertSame(KitchenTicketStatus::NEW->value, $b1Ticket->status);
        $this->assertCount(2, $b1Ticket->items);

        $this->assertDatabaseHas('activity_logs', [
            'branch_id' => $this->branch1->id,
            'category' => 'pos',
            'event' => 'pos_checkout',
            'subject_id' => $b1Order->id,
            'status' => 'success',
        ]);

        // 3. Branch 2 Cashier checks out a separate Branch 2 order
        Livewire::actingAs($this->cashier2)
            ->test(CheckoutModal::class)
            ->call('openWithData', [
                'items' => [
                    ['meal_id' => $b2Dish->id, 'quantity' => 1],
                ],
                'order_type' => OrderType::TAKEOUT->value,
                'customer_name' => 'Cebu Guest',
                'subtotal' => 450.00,
                'total_amount' => 450.00,
            ])
            ->set('paymentMethod', PaymentMethod::QR_TRANSFER->value)
            ->set('referenceNumber', 'GCASH-B2-991')
            ->call('processCheckout')
            ->assertSet('errorMessage', null);

        $b2Order = Order::where('branch_id', $this->branch2->id)->firstOrFail();
        $b2Ticket = KitchenTicket::where('order_id', $b2Order->id)->firstOrFail();

        // 4. Cross-branch isolation on KitchenDisplay and OrderProgress
        Livewire::actingAs($this->cook1)
            ->test(KitchenDisplay::class)
            ->assertSee($b1Order->order_number)
            ->assertSee('B1 Signature Tapsilog')
            ->assertDontSee($b2Order->order_number)
            ->assertDontSee('B2 Cebu Lechon Belly');

        Livewire::actingAs($this->cook2)
            ->test(KitchenDisplay::class)
            ->assertSee($b2Order->order_number)
            ->assertSee('B2 Cebu Lechon Belly')
            ->assertDontSee($b1Order->order_number)
            ->assertDontSee('B1 Signature Tapsilog');

        Livewire::actingAs($this->cashier2)
            ->test(OrderProgress::class)
            ->dispatch('open-order-progress')
            ->assertSee($b2Order->order_number)
            ->assertSee('Cebu Guest')
            ->assertDontSee($b1Order->order_number)
            ->assertDontSee('Maria Clara');

        // 5. Cook 1 claims, toggles first item, marks ready, and marks served
        $firstTicketItem = $b1Ticket->items->first();
        Livewire::actingAs($this->cook1)
            ->test(KitchenDisplay::class)
            ->call('claimTicket', $b1Ticket->id)
            ->call('toggleItem', $firstTicketItem->id)
            ->call('markReady', $b1Ticket->id)
            ->call('markServed', $b1Ticket->id);

        $b1Ticket->refresh();
        $b1Order->refresh();
        $this->assertSame(KitchenTicketStatus::SERVED->value, $b1Ticket->status);
        $this->assertNotNull($b1Ticket->served_at);
        $this->assertSame(OrderStatus::COMPLETED->value, $b1Order->status);
        $this->assertNotNull($b1Order->completed_at);

        $this->assertDatabaseHas('activity_logs', [
            'branch_id' => $this->branch1->id,
            'category' => 'kitchen',
            'event' => 'kitchen_ticket_claimed',
            'subject_id' => $b1Ticket->id,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'branch_id' => $this->branch1->id,
            'category' => 'kitchen',
            'event' => 'kitchen_ticket_status',
            'subject_id' => $b1Ticket->id,
        ]);

        // 6. Cashier 1's POS OrderProgress receives status update event and sees ticket as served
        Livewire::actingAs($this->cashier1)
            ->test(OrderProgress::class)
            ->dispatch('kitchen-ticket-status-updated')
            ->dispatch('open-order-progress')
            ->assertSet('counts.served', 1)
            ->assertSet('counts.ready', 0)
            ->assertSee($b1Order->order_number)
            ->assertSee('Maria Clara')
            ->assertDontSee($b2Order->order_number)
            ->assertDontSee('Cebu Guest');

        // 7. Floor PIN session cannot access audit-report or export-csv
        $this->actingAs($this->cashier1)
            ->withSession([SystemConstants::SESSION_AUTH_METHOD => 'pin'])
            ->get(route('sales-records.audit-report'))
            ->assertStatus(403);

        // 8. Branch 1 Manager (password session) views SalesAggregation, audit-report, and CSV (tampered branchId=branch2 ignored)
        session()->forget(SystemConstants::SESSION_AUTH_METHOD);
        $this->actingAs($this->manager1);
        $service = app(SalesAggregationService::class);
        $service->clearRuntimeCache();

        $summary = $service->getSummary(null, null, $this->branch2->id, null, false);
        $this->assertSame(1, $summary['orders_count']);
        $this->assertSame(450.00, $summary['net_sales']);

        $auditResponse = $this->actingAs($this->manager1)
            ->withSession([SystemConstants::SESSION_AUTH_METHOD => 'password'])
            ->get(route('sales-records.audit-report', [
                'interval' => 'day',
                'branchId' => $this->branch2->id,
            ]));
        $auditResponse->assertStatus(200);
        $auditResponse->assertSee('450.00');

        $csvResponse = $this->actingAs($this->manager1)
            ->withSession([SystemConstants::SESSION_AUTH_METHOD => 'password'])
            ->get(route('sales-records.export-csv', [
                'branchId' => $this->branch2->id,
            ]));
        $csvResponse->assertStatus(200);
        $csvContent = $csvResponse->streamedContent();
        $this->assertStringContainsString($b1Order->order_number, $csvContent);
        $this->assertStringNotContainsString($b2Order->order_number, $csvContent);
    }

    public function test_tier3_manager_pin_void_restores_stock_bumps_menu_version_updates_reports_and_logs_without_pin_leak(): void
    {
        RateLimiter::clear('void-pin-approval:127.0.0.1:B01-MGR-001');
        RateLimiter::clear('void-pin-approval:127.0.0.1:B02-MGR-001');

        ['meal' => $b1Tracked] = $this->createBranchCategoryAndMeal($this->branch1, [
            'name' => 'B1 Tracked Kare-Kare',
            'price' => 300.00,
            'track_stock' => true,
            'stock_quantity' => 10,
        ]);

        $b1Ticket = $this->createPaidTicketViaActions($this->cashier1, [
            'table_number' => '4',
        ], $b1Tracked, 3);
        $b1Order = $b1Ticket->order()->firstOrFail();

        $this->assertSame(7, (int) $b1Tracked->fresh()->stock_quantity);
        $versionBeforeVoid = (int) Cache::get("branch_{$this->branch1->id}_menu_version", 0);

        $secSpy = \Mockery::spy(LoggerInterface::class);
        $logSpy = Log::spy();
        $logSpy->shouldReceive('channel')->with('security')->andReturn($secSpy);
        $logSpy->shouldReceive('channel')->andReturn(\Mockery::spy(LoggerInterface::class));

        // 1. Wrong PIN fails and logs failure without plaintext PIN
        $modal = Livewire::actingAs($this->cashier1)->test(ManagerPinModal::class);
        $modal->call('submitVoidRequest', [
            'order_id' => $b1Order->id,
            'manager_id' => $this->manager1->emp_id,
            'manager_pin' => '0000',
            'reason' => 'Customer changed mind',
        ])->assertReturned(false);

        $this->assertNotNull($modal->get('errorMessage'));
        $this->assertSame(OrderStatus::IN_KITCHEN->value, $b1Order->fresh()->status);

        // 2. Branch 2 Manager PIN cannot void Branch 1 order
        $modal->call('submitVoidRequest', [
            'order_id' => $b1Order->id,
            'manager_id' => $this->manager2->emp_id,
            'manager_pin' => '8888',
            'reason' => 'Cross-branch void attempt',
        ])->assertReturned(false);

        $this->assertSame(OrderStatus::IN_KITCHEN->value, $b1Order->fresh()->status);

        // 3. Branch 1 Manager approves void via ManagerPinModal
        $modal->call('submitVoidRequest', [
            'order_id' => $b1Order->id,
            'manager_id' => $this->manager1->emp_id,
            'manager_pin' => '9999',
            'reason' => 'Duplicate order entry',
        ])
            ->assertReturned(true)
            ->assertSet('errorMessage', null)
            ->assertDispatched('order-voided', orderId: $b1Order->id);

        $b1Order->refresh();
        $b1Ticket->refresh();
        $b1Payment = Payment::where('order_id', $b1Order->id)->firstOrFail();

        $this->assertSame(OrderStatus::CANCELLED->value, $b1Order->status);
        $this->assertSame(PaymentStatus::REFUNDED->value, $b1Order->payment_status);
        $this->assertSame(PaymentStatus::REFUNDED->value, $b1Payment->status);
        $this->assertSame(KitchenTicketStatus::CANCELLED->value, $b1Ticket->status);

        // Stock restored to 10
        $this->assertSame(10, (int) $b1Tracked->fresh()->stock_quantity);

        // Menu version cache bumped on stock restoration (M2 requirement)
        $versionAfterVoid = (int) Cache::get("branch_{$this->branch1->id}_menu_version", 0);
        $this->assertGreaterThan($versionBeforeVoid, $versionAfterVoid, 'VoidOrderAction must bump branch menu version cache when restoring stock.');

        // ActivityLog recorded order_void
        $this->assertDatabaseHas('activity_logs', [
            'branch_id' => $this->branch1->id,
            'category' => 'pos',
            'event' => 'order_void',
            'subject_id' => $b1Order->id,
            'status' => 'success',
        ]);

        // Security channel logged failure & approval without leaking PIN
        $secSpy->shouldHaveReceived('warning')->with(
            'MANAGER_PIN_VOID_ATTEMPT_FAILED',
            \Mockery::on(fn (array $ctx) => ! str_contains(json_encode($ctx) ?: '', '0000'))
        );
        $secSpy->shouldHaveReceived('warning')->with(
            'ORDER_VOID_AUTHORIZED',
            \Mockery::on(fn (array $ctx) => $ctx['order_id'] === $b1Order->id && ! str_contains(json_encode($ctx) ?: '', '9999'))
        );
        $secSpy->shouldHaveReceived('warning')->with(
            'MANAGER_PIN_VOID_APPROVED',
            \Mockery::on(fn (array $ctx) => $ctx['order_id'] === $b1Order->id && ! str_contains(json_encode($ctx) ?: '', '9999'))
        );

        // SalesAggregationService excludes voided order from net_sales and records it in refunds_summary
        $this->actingAs($this->manager1);
        $service = app(SalesAggregationService::class);
        $service->clearRuntimeCache();
        $summary = $service->getSummary(null, null, null, null, false);
        $this->assertSame(0, $summary['orders_count']);
        $this->assertSame(0.00, $summary['net_sales']);
        $this->assertSame(1, $summary['refunds_summary']['refunded_count']);
        $this->assertSame(900.00, $summary['refunds_summary']['refunded_amount']);
    }

    public function test_tier3_terminal_lock_blocks_pos_and_admin_unlocks_with_override_and_logout_routes_by_context(): void
    {
        // 1. Floor cashier locks terminal
        $this->actingAs($this->cashier1)
            ->withSession([SystemConstants::SESSION_AUTH_METHOD => 'pin'])
            ->post(route('terminal.lock'), ['return_url' => '/pos'])
            ->assertRedirect(route('terminal.lock.show'));

        $this->actingAs($this->cashier1)
            ->get(route('terminal.lock.show'))
            ->assertStatus(200);

        // EnsureTerminalUnlocked blocks /pos, /menu, /kitchen, and JSON requests while /admin remains 403 for floor PIN sessions
        $this->actingAs($this->cashier1)->get('/pos')->assertRedirect(route('terminal.lock.show'));
        $this->actingAs($this->cashier1)->get('/admin')->assertStatus(403);
        $this->actingAs($this->cashier1)->get('/menu')->assertRedirect(route('terminal.lock.show'));
        $this->actingAs($this->cashier1)->get('/kitchen')->assertRedirect(route('terminal.lock.show'));
        $this->actingAs($this->cashier1)->getJson('/pos')->assertStatus(423)->assertJson(['locked' => true]);

        // Cross-branch manager override fails
        $this->actingAs($this->cashier1)
            ->post(route('terminal.unlock'), [
                'unlock_mode' => 'override',
                'emp_id' => $this->manager2->emp_id,
                'pin' => '8888',
            ])
            ->assertSessionHasErrors('credential');
        $this->assertTrue((bool) session(SystemConstants::SESSION_TERMINAL_LOCKED));

        // Same-branch manager override unlocks and returns to /pos
        $this->actingAs($this->cashier1)
            ->post(route('terminal.unlock'), [
                'unlock_mode' => 'override',
                'emp_id' => $this->manager1->emp_id,
                'pin' => '9999',
            ])
            ->assertRedirect('/pos');

        $this->assertFalse((bool) session(SystemConstants::SESSION_TERMINAL_LOCKED));
        $this->actingAs($this->cashier1)->get('/pos')->assertStatus(200);

        // Floor logout redirects to /login and logs clock_out
        $this->actingAs($this->cashier1)
            ->withSession([SystemConstants::SESSION_AUTH_METHOD => 'pin'])
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $this->cashier1->id,
            'category' => 'attendance',
            'event' => 'clock_out',
        ]);

        // 2. Manager on floor station locks /pos, unlocks with password, and logout redirects to /admin/login
        $this->actingAs($this->manager1)
            ->post(route('terminal.lock'), ['return_url' => '/pos'])
            ->assertRedirect(route('terminal.lock.show'));

        $this->actingAs($this->manager1)->get('/pos')->assertRedirect(route('terminal.lock.show'));

        $this->actingAs($this->manager1)
            ->post(route('terminal.unlock'), [
                'unlock_mode' => 'self',
                'password' => 'password',
            ])
            ->assertRedirect('/pos');

        $this->actingAs($this->manager1)->get('/pos')->assertStatus(200);

        $this->actingAs($this->manager1)
            ->post(route('logout'))
            ->assertRedirect('/admin/login');

        $this->assertGuest();
    }

    public function test_tier3_menu_availability_and_portions_sync_to_pos_catalog_and_enforce_server_checkout_rejection(): void
    {
        ['meal' => $globalMeal] = $this->createBranchCategoryAndMeal(null, [
            'name' => 'Global Crispy Pata',
            'price' => 650.00,
        ]);
        ['meal' => $b1Meal] = $this->createBranchCategoryAndMeal($this->branch1, [
            'name' => 'B1 Special Pancit',
            'price' => 220.00,
        ]);

        $b1Catalog = Livewire::actingAs($this->cashier1)->test(MealCatalog::class);
        $b2Catalog = Livewire::actingAs($this->cashier2)->test(MealCatalog::class);

        // 1. Chef 1 sets Branch 1 portion limit = 2 on the global meal
        $this->actingAs($this->chef1);
        app(UpdateBranchPortionsAction::class)->handle($globalMeal->id, true, 2);

        $this->actingAs($this->cashier1);
        $b1Catalog->call('syncAvailability')
            ->assertDispatched('meal-availability-synced');

        $this->actingAs($this->cashier2);
        $b2Catalog->call('syncAvailability')
            ->assertNotDispatched('meal-availability-synced');

        $freshGlobal = $globalMeal->fresh();
        $this->assertTrue($freshGlobal->trackStockForBranch($this->branch1->id));
        $this->assertSame(2, $freshGlobal->stockQuantityForBranch($this->branch1->id));
        $this->assertFalse($freshGlobal->trackStockForBranch($this->branch2->id));

        // 2. Cashier 1 cannot check out qty 3 when Branch 1 has only 2 portions
        Livewire::actingAs($this->cashier1)
            ->test(CheckoutModal::class)
            ->call('openWithData', [
                'items' => [['meal_id' => $globalMeal->id, 'quantity' => 3]],
                'order_type' => OrderType::DINE_IN->value,
                'table_number' => '1',
                'subtotal' => 1950.00,
                'total_amount' => 1950.00,
            ])
            ->set('amountTendered', '2000.00')
            ->call('processCheckout')
            ->assertNotSet('errorMessage', null);

        $this->assertSame(0, Order::count());

        // 3. Cashier 1 checks out qty 2 -> succeeds and depletes Branch 1 stock to 0 (86'd in Branch 1 only)
        Livewire::actingAs($this->cashier1)
            ->test(CheckoutModal::class)
            ->call('openWithData', [
                'items' => [['meal_id' => $globalMeal->id, 'quantity' => 2]],
                'order_type' => OrderType::DINE_IN->value,
                'table_number' => '1',
                'subtotal' => 1300.00,
                'total_amount' => 1300.00,
            ])
            ->set('amountTendered', '1500.00')
            ->call('processCheckout')
            ->assertSet('errorMessage', null);

        $this->assertTrue($globalMeal->fresh()->isSoldOutForBranch($this->branch1->id));
        $this->assertFalse($globalMeal->fresh()->isSoldOutForBranch($this->branch2->id));

        // Subsequent Branch 1 checkout for depleted meal is rejected
        Livewire::actingAs($this->cashier1)
            ->test(CheckoutModal::class)
            ->call('openWithData', [
                'items' => [['meal_id' => $globalMeal->id, 'quantity' => 1]],
                'order_type' => OrderType::DINE_IN->value,
                'table_number' => '2',
                'subtotal' => 650.00,
                'total_amount' => 650.00,
            ])
            ->set('amountTendered', '700.00')
            ->call('processCheckout')
            ->assertNotSet('errorMessage', null);

        // Branch 2 can still buy the global meal
        Livewire::actingAs($this->cashier2)
            ->test(CheckoutModal::class)
            ->call('openWithData', [
                'items' => [['meal_id' => $globalMeal->id, 'quantity' => 1]],
                'order_type' => OrderType::DINE_IN->value,
                'table_number' => '3',
                'subtotal' => 650.00,
                'total_amount' => 650.00,
            ])
            ->set('amountTendered', '700.00')
            ->call('processCheckout')
            ->assertSet('errorMessage', null);

        // 4. Chef 1 marks B1 meal 86'd via ToggleMealAvailabilityAction -> POS CheckoutModal rejects it
        $this->actingAs($this->chef1);
        app(ToggleMealAvailabilityAction::class)->handle($b1Meal);

        $this->assertDatabaseHas('activity_logs', [
            'branch_id' => $this->branch1->id,
            'category' => 'menu',
            'event' => 'menu_availability_toggled',
            'subject_id' => $b1Meal->id,
        ]);

        Livewire::actingAs($this->cashier1)
            ->test(CheckoutModal::class)
            ->call('openWithData', [
                'items' => [['meal_id' => $b1Meal->id, 'quantity' => 1]],
                'order_type' => OrderType::TAKEOUT->value,
                'subtotal' => 220.00,
                'total_amount' => 220.00,
            ])
            ->set('amountTendered', '300.00')
            ->call('processCheckout')
            ->assertNotSet('errorMessage', null);
    }
}
