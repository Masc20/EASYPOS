<?php

namespace Tests\Feature\E2E;

use App\Domains\Identity\Models\User;
use App\Domains\Kitchen\Livewire\KitchenDisplay;
use App\Domains\Kitchen\Models\KitchenTicket;
use App\Domains\Menu\Models\Meal;
use App\Domains\Menu\Models\MealCategory;
use App\Domains\PointOfSale\Actions\VoidOrderAction;
use App\Domains\PointOfSale\Livewire\Checkout\CheckoutModal;
use App\Domains\PointOfSale\Livewire\Checkout\ManagerPinModal;
use App\Domains\PointOfSale\Livewire\OrderProgress;
use App\Domains\PointOfSale\Models\Order;
use App\Domains\PointOfSale\Services\SalesAggregationService;
use App\Support\Enums\KitchenTicketStatus;
use App\Support\Enums\OrderStatus;
use App\Support\Enums\OrderType;
use App\Support\Enums\PaymentMethod;
use App\Support\Enums\PaymentStatus;
use App\Support\Enums\StaffRole;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Psr\Log\LoggerInterface;
use Tests\Feature\E2E\Concerns\E2ETestHelpers;
use Tests\TestCase;

class Tier4ResilienceAndPerformanceE2ETest extends TestCase
{
    use E2ETestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedE2EActors();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_tier4_multi_branch_shift_workload_reconciles_discounts_methods_voids_and_csv_exports(): void
    {
        ['meal' => $globalMeal] = $this->createBranchCategoryAndMeal(null, [
            'name' => 'Global Classic Sisig',
            'price' => 200.00,
        ]);
        ['meal' => $b1Meal] = $this->createBranchCategoryAndMeal($this->branch1, [
            'name' => 'B1 Halo-Halo',
            'price' => 150.00,
        ]);
        ['meal' => $b1Tracked] = $this->createBranchCategoryAndMeal($this->branch1, [
            'name' => 'B1 Limited Steak',
            'price' => 100.00,
            'track_stock' => true,
            'stock_quantity' => 10,
        ]);

        // Branch 1 Order 1: Dine-in, Global meal x2 (400), Senior/PWD 20% discount (-80 = 320 net), Cash
        $t1 = $this->createPaidTicketViaActions($this->cashier1, [
            'order_type' => OrderType::DINE_IN->value,
            'table_number' => '3',
            'discount_type' => 'senior_pwd',
            'payment_method' => PaymentMethod::CASH->value,
            'amount_tendered' => 500.00,
        ], $globalMeal, 2);

        // Branch 1 Order 2: Takeout, B1 meal x1 (150), no discount, GCash / QR
        $t2 = $this->createPaidTicketViaActions($this->cashier1, [
            'order_type' => OrderType::TAKEOUT->value,
            'payment_method' => PaymentMethod::QR_TRANSFER->value,
            'reference_number' => 'GCASH-SHIFT-02',
        ], $b1Meal, 1);

        // Branch 1 Order 3: Dine-in, B1 tracked x2 (200), Card -> later voided
        $t3 = $this->createPaidTicketViaActions($this->cashier1, [
            'order_type' => OrderType::DINE_IN->value,
            'table_number' => '9',
            'payment_method' => PaymentMethod::CARD->value,
            'reference_number' => 'CARD-SHIFT-03',
        ], $b1Tracked, 2);

        $this->assertSame(8, (int) $b1Tracked->fresh()->stock_quantity);

        // Branch 2 Order 4: Dine-in, Global meal x3 (600), Cash
        $t4 = $this->createPaidTicketViaActions($this->cashier2, [
            'order_type' => OrderType::DINE_IN->value,
            'table_number' => '1',
            'payment_method' => PaymentMethod::CASH->value,
            'amount_tendered' => 600.00,
        ], $globalMeal, 3);

        // Partial shift void on Order 3 by Branch 1 Manager
        $voidedOrder = app(VoidOrderAction::class)->execute([
            'order_id' => $t3->order_id,
            'manager_id' => $this->manager1->emp_id,
            'manager_pin' => '9999',
            'reason' => 'Kitchen out of garnish',
            'cashier_id' => $this->cashier1->id,
            'skip_pin_check' => false,
        ]);

        $this->assertSame(OrderStatus::CANCELLED->value, $voidedOrder->status);
        $this->assertSame(10, (int) $b1Tracked->fresh()->stock_quantity);

        // Reconcile Branch 1 Manager SalesAggregationService
        $this->actingAs($this->manager1);
        $service = app(SalesAggregationService::class);
        $service->clearRuntimeCache();

        $b1Summary = $service->getSummary(null, null, null, null, false);
        $this->assertSame(2, $b1Summary['orders_count']);
        $this->assertSame(550.00, $b1Summary['gross_sales']);
        $this->assertSame(80.00, $b1Summary['total_discounts']);
        $this->assertSame(470.00, $b1Summary['net_sales']);
        $this->assertSame(1, $b1Summary['refunds_summary']['refunded_count']);
        $this->assertSame(200.00, $b1Summary['refunds_summary']['refunded_amount']);
        $this->assertSame(320.00, $b1Summary['cash_reconciliation']['net_cash_in_drawer']);
        $this->assertSame(150.00, $b1Summary['cash_reconciliation']['total_digital']);
        $this->assertSame(320.00, $b1Summary['tax_summary']['vat_exempt_sales']);
        $this->assertSame(round(150.00 / 1.12, 2), $b1Summary['tax_summary']['vatable_sales']);
        $this->assertSame(1, $b1Summary['order_types_breakdown']['dine_in']);
        $this->assertSame(1, $b1Summary['order_types_breakdown']['takeout']);

        // Reconcile Daily & Periodic CSV exports (branch-scoped)
        $ordersCsv = $service->exportCsv();
        $this->assertStringContainsString($t1->order->order_number, $ordersCsv);
        $this->assertStringContainsString($t2->order->order_number, $ordersCsv);
        $this->assertStringNotContainsString($t3->order->order_number, $ordersCsv);
        $this->assertStringNotContainsString($t4->order->order_number, $ordersCsv);

        $periodicCsv = $service->exportPeriodicCsv('day');
        $this->assertStringContainsString('"TOTAL / SUMMARY",2,550.00,80.00,470.00', $periodicCsv);

        // Owner sees cross-branch active totals (Order 1 + Order 2 + Order 4 = 3 orders, 1070.00 net)
        $this->actingAs($this->owner);
        $service->clearRuntimeCache();
        $globalSummary = $service->getSummary(null, null, null, null, false);
        $this->assertSame(3, $globalSummary['orders_count']);
        $this->assertSame(1070.00, $globalSummary['net_sales']);
    }

    public function test_tier4_kds_overflow_and_order_progress_scale_enforce_caps_query_budget_and_branch_isolation(): void
    {
        $b1Tickets = $this->createTicketsForBranch(80, $this->branch1->id, $this->cashier1, KitchenTicketStatus::NEW->value);
        $b2Tickets = $this->createTicketsForBranch(15, $this->branch2->id, $this->cashier2, KitchenTicketStatus::NEW->value);

        $this->assertCount(80, $b1Tickets);
        $this->assertCount(15, $b2Tickets);

        // 1. KitchenDisplay active cap <= 75, overflowCount == 5, bounded queries, zero Branch 2 leakage
        DB::flushQueryLog();
        DB::enableQueryLog();

        $kds = Livewire::actingAs($this->cook1)->test(KitchenDisplay::class);
        $kdsQueries = DB::getQueryLog();
        DB::disableQueryLog();

        $kds->assertViewHas('totalActiveCount', 80)
            ->assertViewHas('overflowCount', 5)
            ->assertViewHas('newTickets', fn ($tickets) => $tickets->count() <= 75)
            ->assertDontSee($b2Tickets[0]->ticket_number);

        $renderedActive = $kds->viewData('newTickets')->count()
            + $kds->viewData('inPrepTickets')->count()
            + $kds->viewData('readyTickets')->count();
        $this->assertSame(75, $renderedActive);
        $this->assertLessThanOrEqual(25, count($kdsQueries), 'KitchenDisplay should not suffer N+1 query explosion under 80 tickets.');

        // 2. OrderProgress active cap <= 50, accurate total count = 80, bounded queries, zero Branch 2 leakage
        DB::flushQueryLog();
        DB::enableQueryLog();

        $op = Livewire::actingAs($this->cashier1)
            ->test(OrderProgress::class)
            ->dispatch('open-order-progress');

        $opQueries = DB::getQueryLog();
        DB::disableQueryLog();

        $op->assertSet('counts.new', 80)
            ->assertSet('counts.all', 80)
            ->assertViewHas('tickets', fn ($tickets) => $tickets->count() <= 50 && $tickets->every(fn (KitchenTicket $t) => (int) $t->branch_id === (int) $this->branch1->id))
            ->assertDontSee($b2Tickets[0]->ticket_number);

        $this->assertLessThanOrEqual(20, count($opQueries), 'OrderProgress should not suffer N+1 query explosion under 80 tickets.');
    }

    public function test_tier4_security_log_channel_failure_does_not_abort_void_transaction_or_pin_modal(): void
    {
        RateLimiter::clear('void-pin-approval:127.0.0.1:B01-MGR-001');

        ['meal' => $tracked] = $this->createBranchCategoryAndMeal($this->branch1, [
            'name' => 'Resilient Stock Meal',
            'price' => 250.00,
            'track_stock' => true,
            'stock_quantity' => 5,
        ]);

        $ticket = $this->createPaidTicketViaActions($this->cashier1, [], $tracked, 2);
        $order = $ticket->order()->firstOrFail();
        $this->assertSame(3, (int) $tracked->fresh()->stock_quantity);

        // Simulate catastrophic disk/permission failure on Log::channel('security')
        $logSpy = Log::spy();
        $logSpy->shouldReceive('channel')
            ->with('security')
            ->andThrow(new \RuntimeException('Simulated security log channel failure'));
        $logSpy->shouldReceive('channel')
            ->andReturn(\Mockery::spy(LoggerInterface::class));

        $modal = Livewire::actingAs($this->cashier1)->test(ManagerPinModal::class);

        // Failed PIN still returns false gracefully without throwing RuntimeException
        $modal->call('submitVoidRequest', [
            'order_id' => $order->id,
            'manager_id' => $this->manager1->emp_id,
            'manager_pin' => '0000',
            'reason' => 'Test outage resilience',
        ])->assertReturned(false);
        $this->assertNotNull($modal->get('errorMessage'));

        // Valid PIN void still commits DB transaction, restores stock, and records ActivityLog
        $modal->call('submitVoidRequest', [
            'order_id' => $order->id,
            'manager_id' => $this->manager1->emp_id,
            'manager_pin' => '9999',
            'reason' => 'Void during log outage',
        ])
            ->assertReturned(true)
            ->assertSet('errorMessage', null);

        $this->assertSame(OrderStatus::CANCELLED->value, $order->fresh()->status);
        $this->assertSame(PaymentStatus::REFUNDED->value, $order->fresh()->payment_status);
        $this->assertSame(KitchenTicketStatus::CANCELLED->value, $ticket->fresh()->status);
        $this->assertSame(5, (int) $tracked->fresh()->stock_quantity);

        $this->assertDatabaseHas('activity_logs', [
            'branch_id' => $this->branch1->id,
            'category' => 'pos',
            'event' => 'order_void',
            'subject_id' => $order->id,
        ]);
    }

    public function test_tier4_shared_fixture_trait_creates_isolated_valid_domain_graphs_across_all_four_methods(): void
    {
        ['category' => $cat, 'meal' => $meal] = $this->createBranchCategoryAndMeal($this->branch1, ['price' => 175.00]);
        $this->assertInstanceOf(MealCategory::class, $cat);
        $this->assertInstanceOf(Meal::class, $meal);
        $this->assertSame($this->branch1->id, (int) $meal->branch_id);

        $actionTicket = $this->createPaidTicketViaActions($this->cashier1, ['table_number' => '8'], $meal, 2, 'No onions');
        $this->assertSame($this->branch1->id, (int) $actionTicket->branch_id);
        $this->assertSame(350.00, (float) $actionTicket->order->total_amount);
        $this->assertSame('No onions', $actionTicket->items->first()->orderItem->special_instructions);

        $direct = $this->createOrderWithKitchenTicket(
            $this->branch2->id,
            $this->cashier2,
            null,
            ['table_number' => '12'],
            ['priority' => 'rush'],
            ['quantity' => 3]
        );
        $this->assertArrayHasKeys(['order', 'order_item', 'payment', 'ticket', 'ticket_item'], $direct);
        $this->assertSame($this->branch2->id, (int) $direct['order']->branch_id);
        $this->assertSame(3, (int) $direct['order_item']->quantity);
        $this->assertSame('rush', $direct['ticket']->priority);

        $batch = $this->createTicketsForBranch(4, $this->branch2->id, $this->cashier2, KitchenTicketStatus::READY->value);
        $this->assertCount(4, $batch);
        foreach ($batch as $ticket) {
            $this->assertSame($this->branch2->id, (int) $ticket->branch_id);
            $this->assertSame(KitchenTicketStatus::READY->value, $ticket->status);
        }
    }

    public function test_tier4_null_branch_scoped_actors_fail_closed_across_kds_pos_and_sales_aggregation(): void
    {
        ['meal' => $b1Meal] = $this->createBranchCategoryAndMeal($this->branch1, ['price' => 300.00]);
        $b1Ticket = $this->createPaidTicketViaActions($this->cashier1, ['table_number' => '6'], $b1Meal, 1);

        $nullManager = User::create([
            'name' => 'Unassigned Manager',
            'email' => 'unassigned-mgr-t4@easypos.test',
            'emp_id' => 'NULL-MGR-T4',
            'branch_id' => null,
            'password' => Hash::make('Str0ng!Pass#2026'),
            'pin_code' => Hash::make('7777'),
            'is_active' => true,
        ]);
        $nullManager->assignRole(StaffRole::BRANCH_MANAGER->value);

        $nullCook = User::create([
            'name' => 'Unassigned Cook',
            'email' => 'unassigned-cook-t4@easypos.test',
            'emp_id' => 'NULL-COK-T4',
            'branch_id' => null,
            'password' => Hash::make('Str0ng!Pass#2026'),
            'pin_code' => Hash::make('6666'),
            'is_active' => true,
        ]);
        $nullCook->assignRole(StaffRole::COOK->value);

        $nullCashier = User::create([
            'name' => 'Unassigned Cashier',
            'email' => 'unassigned-csh-t4@easypos.test',
            'emp_id' => 'NULL-CSH-T4',
            'branch_id' => null,
            'password' => Hash::make('Str0ng!Pass#2026'),
            'pin_code' => Hash::make('5555'),
            'is_active' => true,
        ]);
        $nullCashier->assignRole(StaffRole::CASHIER->value);

        // 1. Null-branch manager cannot read Branch 1 orders via SalesAggregationService
        $this->actingAs($nullManager);
        $service = app(SalesAggregationService::class);
        $service->clearRuntimeCache();

        $summary = $service->getSummary(null, null, null, null, false);
        $this->assertSame(0, $summary['orders_count'], 'Null-branch manager must fail closed and see 0 orders.');
        $this->assertSame(0.00, $summary['net_sales']);
        $this->assertStringNotContainsString($b1Ticket->order->order_number, $service->exportCsv());

        // 2. Null-branch cook must NOT see Branch 1 tickets in KitchenDisplay (fails closed with 403)
        try {
            Livewire::actingAs($nullCook)
                ->test(KitchenDisplay::class)
                ->assertDontSee($b1Ticket->ticket_number);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }

        // 3. Null-branch cashier must NOT see Branch 1 tickets in OrderProgress or create Branch 1 orders in CheckoutModal
        Livewire::actingAs($nullCashier)
            ->test(OrderProgress::class)
            ->dispatch('open-order-progress')
            ->assertSet('counts.all', 0)
            ->assertDontSee($b1Ticket->ticket_number);

        $ordersBefore = Order::count();
        Livewire::actingAs($nullCashier)
            ->test(CheckoutModal::class)
            ->call('openWithData', [
                'items' => [['meal_id' => $b1Meal->id, 'quantity' => 1]],
                'order_type' => OrderType::DINE_IN->value,
                'table_number' => '1',
                'subtotal' => 300.00,
                'total_amount' => 300.00,
            ])
            ->set('amountTendered', '500.00')
            ->call('processCheckout')
            ->assertNotSet('errorMessage', null);

        $this->assertSame($ordersBefore, Order::count(), 'Null-branch cashier must not create an order in Branch 1.');
    }

    /**
     * @param  array<int, string>  $keys
     * @param  array<string, mixed>  $array
     */
    private function assertArrayHasKeys(array $keys, array $array): void
    {
        foreach ($keys as $key) {
            $this->assertArrayHasKey($key, $array);
        }
    }
}
