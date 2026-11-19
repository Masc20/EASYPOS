<?php

namespace Tests\Feature\Operations;

use App\Domains\Branches\Models\Branch;
use App\Domains\Identity\Models\User;
use App\Domains\Kitchen\Livewire\KitchenDisplay;
use App\Domains\Kitchen\Models\KitchenTicket;
use App\Domains\Menu\Models\Meal;
use App\Domains\Menu\Models\MealCategory;
use App\Domains\PointOfSale\Livewire\Checkout\ManagerPinModal;
use App\Domains\PointOfSale\Livewire\OrderProgress;
use App\Domains\PointOfSale\Models\Order;
use App\Domains\PointOfSale\Models\Payment;
use App\Filament\Pages\SalesRecords;
use App\Support\Constants\SystemConstants;
use App\Support\Enums\KitchenTicketStatus;
use App\Support\Enums\OrderStatus;
use App\Support\Enums\PaymentStatus;
use App\Support\Enums\StaffRole;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class VoidCsvRecallAndOrderProgressTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch1;
    private Branch $branch2;
    private User $owner;
    private User $superAdmin;
    private User $manager1;
    private User $manager2;
    private User $cashier1;
    private User $cook1;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->branch1 = Branch::firstOrCreate(['code' => 'B01'], ['name' => 'Branch 1', 'is_active' => true]);
        $this->branch2 = Branch::firstOrCreate(['code' => 'B02'], ['name' => 'Branch 2', 'is_active' => true]);

        $this->owner = User::where('email', 'owner@easypos.com')->firstOrFail();
        $this->owner->update(['pin_code' => '1234', 'emp_id' => $this->owner->emp_id ?: 'HQ-OWN-001']);

        $this->superAdmin = User::where('email', 'admin@easypos.com')->firstOrFail();
        $this->superAdmin->update(['pin_code' => '5678', 'branch_id' => null, 'emp_id' => $this->superAdmin->emp_id ?: 'HQ-ADM-001']);

        $this->manager1 = User::where('email', 'mgr-b01@easypos.com')->firstOrFail();
        $this->manager1->update(['pin_code' => '4321']);

        $this->manager2 = User::firstOrCreate(['email' => 'mgr-b02-void@easypos.com'], [
            'name' => 'Branch 2 Manager',
            'emp_id' => 'B02-MGR-099',
            'password' => Hash::make('Password!1234'),
            'pin_code' => '9999',
            'branch_id' => $this->branch2->id,
            'is_active' => true,
        ]);
        $this->manager2->syncRoles([StaffRole::BRANCH_MANAGER->value]);

        $this->cashier1 = User::where('emp_id', 'B01-CSH-001')->firstOrFail();
        $this->cook1 = User::where('emp_id', 'B01-COK-001')->firstOrFail();
    }

    private function createActiveOrderWithTicket(Branch $branch, string $orderNumber, ?\Carbon\Carbon $createdAt = null, string $ticketStatus = 'new', ?\Carbon\Carbon $servedAt = null): array
    {
        $ts = $createdAt ?? now();

        $order = Order::create([
            'branch_id' => $branch->id,
            'cashier_id' => $this->cashier1->id,
            'order_number' => $orderNumber,
            'order_type' => 'dine_in',
            'subtotal' => 200.00,
            'discount_amount' => 0.00,
            'total_amount' => 200.00,
            'status' => $ticketStatus === 'served' ? OrderStatus::COMPLETED->value : OrderStatus::IN_KITCHEN->value,
            'payment_status' => PaymentStatus::PAID->value,
        ]);

        Payment::create([
            'order_id' => $order->id,
            'branch_id' => $branch->id,
            'cashier_id' => $this->cashier1->id,
            'amount' => 200.00,
            'amount_tendered' => 200.00,
            'payment_method' => 'cash',
            'status' => 'successful',
        ]);

        $ticket = KitchenTicket::create([
            'branch_id' => $branch->id,
            'order_id' => $order->id,
            'ticket_number' => 'K-' . $orderNumber,
            'status' => $ticketStatus,
            'priority' => 'normal',
            'served_at' => $servedAt,
        ]);

        if ($createdAt !== null) {
            DB::table('orders')->where('id', $order->id)->update(['created_at' => $ts, 'updated_at' => $ts]);
            DB::table('kitchen_tickets')->where('id', $ticket->id)->update(['created_at' => $ts, 'updated_at' => $ts]);
        }

        return ['order' => $order->fresh(), 'ticket' => $ticket->fresh()];
    }

    public function test_pos_order_void_succeeds_for_manager_super_admin_and_owner_with_reason(): void
    {
        // 1. Same-branch Manager (lowercase emp_id + order_id passed in payload)
        ['order' => $order1, 'ticket' => $ticket1] = $this->createActiveOrderWithTicket($this->branch1, 'ORD-VOID-01');

        Livewire::actingAs($this->cashier1)
            ->test(ManagerPinModal::class)
            ->call('submitVoidRequest', [
                'order_id' => $order1->id,
                'manager_id' => '  ' . strtolower($this->manager1->emp_id) . '  ',
                'manager_pin' => '4321',
                'reason' => 'Customer changed order',
            ])
            ->assertDispatched('close-modal', 'manager-pin-modal')
            ->assertDispatched('order-voided', orderId: $order1->id)
            ->assertDispatched('pos-alert');

        $this->assertSame(OrderStatus::CANCELLED->value, $order1->fresh()->status);
        $this->assertSame(PaymentStatus::REFUNDED->value, $order1->fresh()->payment_status);
        $this->assertSame(KitchenTicketStatus::CANCELLED->value, $ticket1->fresh()->status);

        // 2. Super-Admin (branch_id === null, cross-branch authorized)
        ['order' => $order2] = $this->createActiveOrderWithTicket($this->branch1, 'ORD-VOID-02');

        Livewire::actingAs($this->cashier1)
            ->test(ManagerPinModal::class)
            ->call('openModal', $order2->id)
            ->call('submitVoidRequest', [
                'manager_id' => $this->superAdmin->emp_id,
                'manager_pin' => '5678',
                'reason' => 'HQ Super-Admin override',
            ])
            ->assertDispatched('order-voided', orderId: $order2->id);

        $this->assertSame(OrderStatus::CANCELLED->value, $order2->fresh()->status);

        // 3. Owner
        ['order' => $order3] = $this->createActiveOrderWithTicket($this->branch1, 'ORD-VOID-03');

        Livewire::actingAs($this->cashier1)
            ->test(ManagerPinModal::class)
            ->call('openModal', $order3->id)
            ->call('submitVoidRequest', [
                'manager_id' => $this->owner->emp_id,
                'manager_pin' => '1234',
                'reason' => 'Owner void authorization',
            ])
            ->assertDispatched('order-voided', orderId: $order3->id);

        $this->assertSame(OrderStatus::CANCELLED->value, $order3->fresh()->status);
    }

    public function test_pos_order_void_rejects_wrong_branch_manager_and_invalid_pin_without_server_error(): void
    {
        ['order' => $order] = $this->createActiveOrderWithTicket($this->branch1, 'ORD-VOID-DENY');

        // Wrong-branch manager denied with uniform anti-oracle message
        Livewire::actingAs($this->cashier1)
            ->test(ManagerPinModal::class)
            ->call('openModal', $order->id)
            ->call('submitVoidRequest', [
                'manager_id' => $this->manager2->emp_id,
                'manager_pin' => '9999',
                'reason' => 'Cross branch attempt',
            ])
            ->assertSet('errorMessage', 'Invalid manager PIN.');

        // Non-existent non-numeric emp_id handled gracefully (no SQL integer cast crash, uniform anti-oracle message)
        Livewire::actingAs($this->cashier1)
            ->test(ManagerPinModal::class)
            ->call('openModal', $order->id)
            ->call('submitVoidRequest', [
                'manager_id' => 'NON-EXISTENT-EMP',
                'manager_pin' => '1234',
                'reason' => 'Bad manager id',
            ])
            ->assertSet('errorMessage', 'Invalid manager PIN.');

        $this->assertSame(OrderStatus::IN_KITCHEN->value, $order->fresh()->status);
    }

    public function test_sales_records_csv_export_streams_filtered_rows_with_bounded_queries_and_branch_scoping(): void
    {
        for ($i = 1; $i <= 12; $i++) {
            $this->createActiveOrderWithTicket($this->branch1, sprintf('CSV-B1-%02d', $i), now(), 'served', now());
        }
        $this->createActiveOrderWithTicket($this->branch2, 'CSV-B2-01', now(), 'served', now());

        // Floor PIN session cannot export back-office CSV
        $this->actingAs($this->manager1)
            ->withSession([SystemConstants::SESSION_AUTH_METHOD => 'pin'])
            ->get(route('sales-records.export-csv'))
            ->assertStatus(403);

        // Branch Manager export enforces own branch even with tampered branchId and stays bounded in queries
        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->actingAs($this->manager1)
            ->withSession([SystemConstants::SESSION_AUTH_METHOD => 'password'])
            ->get(route('sales-records.export-csv', [
                'startDate' => now('Asia/Manila')->toDateString(),
                'endDate' => now('Asia/Manila')->toDateString(),
                'branchId' => $this->branch2->id, // tampered
            ]));

        $response->assertStatus(200);
        $csv = $response->streamedContent();
        $queryCount = count(DB::getQueryLog());

        $this->assertStringContainsString('CSV-B1-01', $csv);
        $this->assertStringContainsString('CSV-B1-12', $csv);
        $this->assertStringNotContainsString('CSV-B2-01', $csv);
        $this->assertLessThanOrEqual(8, $queryCount, "Expected bounded SQL queries for CSV export, observed {$queryCount}.");
    }

    public function test_recall_history_shows_only_today_served_tickets_while_order_progress_includes_earlier_active_orders(): void
    {
        $yesterday = now()->subDay();

        // Served yesterday -> excluded from Recall History AND excluded from Order Progress
        ['ticket' => $servedYesterday] = $this->createActiveOrderWithTicket($this->branch1, 'HIST-YEST-SERVED', $yesterday, 'served', $yesterday);

        // Still active (in_prep) from yesterday -> INCLUDED in Order Progress
        ['ticket' => $activeYesterday] = $this->createActiveOrderWithTicket($this->branch1, 'HIST-YEST-ACTIVE', $yesterday, 'in_prep', null);

        // Served today -> INCLUDED in both Recall History and Order Progress
        ['ticket' => $servedToday] = $this->createActiveOrderWithTicket($this->branch1, 'HIST-TODAY-SERVED', now(), 'served', now());

        // Active today -> INCLUDED in Order Progress
        ['ticket' => $activeToday] = $this->createActiveOrderWithTicket($this->branch1, 'HIST-TODAY-ACTIVE', now(), 'new', null);

        // Recall History on KitchenDisplay: strictly today only
        $kds = Livewire::actingAs($this->cook1)
            ->test(KitchenDisplay::class)
            ->call('toggleRecallDrawer');

        $kds->assertSee($servedToday->ticket_number)
            ->assertDontSee($servedYesterday->ticket_number);

        // Order Progress on POS: today's orders + yesterday's still-uncompleted active order, NOT yesterday's served
        $progress = Livewire::actingAs($this->cashier1)
            ->test(OrderProgress::class)
            ->call('open');

        $progress->assertSee($activeToday->ticket_number)
            ->assertSee($servedToday->ticket_number)
            ->assertSee($activeYesterday->ticket_number)
            ->assertDontSee($servedYesterday->ticket_number);
    }
}
