<?php

namespace Tests\Feature\Adversarial;

use App\Domains\Branches\Models\Branch;
use App\Domains\Identity\Models\User;
use App\Domains\PointOfSale\Models\Order;
use App\Domains\PointOfSale\Models\Payment;
use App\Domains\PointOfSale\Services\SalesAggregationService;
use App\Filament\Pages\SalesRecords;
use App\Support\Enums\OrderStatus;
use App\Support\Enums\PaymentStatus;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdversarialM3SalesRecordsRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branch1;
    protected Branch $branch2;
    protected User $admin;
    protected User $owner;
    protected User $manager1;
    protected User $manager2;
    protected User $cashier1;
    protected User $cashier2;
    protected User $multibyteCashier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->branch1 = Branch::firstOrCreate(['code' => 'B01'], ['name' => 'Branch One', 'is_active' => true]);
        $this->branch2 = Branch::firstOrCreate(['code' => 'B02'], ['name' => 'Branch Two', 'is_active' => true]);

        $this->admin = User::where('email', 'admin@easypos.com')->firstOrFail();
        $this->owner = User::where('email', 'owner@easypos.com')->firstOrFail();

        $this->manager1 = User::firstOrCreate(['email' => 'mgr1@test.com'], [
            'name' => 'Manager One',
            'emp_id' => 'B01-MGR-999',
            'branch_id' => $this->branch1->id,
            'password' => bcrypt('password'),
        ]);
        $this->manager1->assignRole('branch-manager');

        $this->manager2 = User::firstOrCreate(['email' => 'mgr2@test.com'], [
            'name' => 'Manager Two',
            'emp_id' => 'B02-MGR-999',
            'branch_id' => $this->branch2->id,
            'password' => bcrypt('password'),
        ]);
        $this->manager2->assignRole('branch-manager');

        $this->cashier1 = User::where('emp_id', 'B01-CSH-001')->firstOrFail();
        $this->cashier2 = User::firstOrCreate(['email' => 'csh2@test.com'], [
            'name' => 'Cashier Two',
            'emp_id' => 'B02-CSH-999',
            'branch_id' => $this->branch2->id,
            'password' => bcrypt('password'),
        ]);
        $this->cashier2->assignRole('cashier');

        // Multibyte named staff to stress mb_substr
        $this->multibyteCashier = User::firstOrCreate(['email' => 'mb_csh@test.com'], [
            'name' => 'Ñiño 🌟 Staff',
            'emp_id' => 'B01-CSH-998',
            'branch_id' => $this->branch1->id,
            'password' => bcrypt('password'),
        ]);
        $this->multibyteCashier->assignRole('cashier');
    }

    public function test_view_data_pruning_leaves_no_missing_bindings_or_render_errors(): void
    {
        // Zero orders scenario - stress all null checks & partials
        $testable = Livewire::actingAs($this->admin)
            ->test(SalesRecords::class)
            ->assertSuccessful()
            ->assertViewHas('summary')
            ->assertViewHas('periodicReport')
            ->assertViewHas('branches')
            ->assertViewHas('cashiers')
            ->assertViewHas('isBranchScoped', false);

        $viewData = $testable->instance()->render()->getData();
        $this->assertArrayNotHasKey('bestSellingRankings', $viewData);
        $this->assertArrayNotHasKey('categoryRankings', $viewData);
        $this->assertArrayNotHasKey('hourlyDistribution', $viewData);
        $this->assertArrayNotHasKey('categories', $viewData);

        // Assert rendered partials contain expected baseline components
        $testable->assertSee('Gross Sales')
            ->assertSee('Net Revenue')
            ->assertSee('Discounts')
            ->assertSee('Paid Orders')
            ->assertSee('Avg Ticket (AOV)')
            ->assertSee('Cash in Drawer')
            ->assertSee('Payment Tender Distribution')
            ->assertSee('Cash Register Reconciliation')
            ->assertSee('BIR Tax Compliance Breakdown')
            ->assertSee('Periodic Sales Reports')
            ->assertSee('Export Periodic CSV');
    }

    public function test_all_filter_presets_and_date_switches_render_cleanly(): void
    {
        $testable = Livewire::actingAs($this->admin)->test(SalesRecords::class);

        $presets = ['today', 'yesterday', 'this_week', 'this_month', 'this_year', 'all_time', 'custom'];
        foreach ($presets as $preset) {
            $testable->call('setPreset', $preset)
                ->assertSet('selectedPreset', $preset)
                ->assertSuccessful();
        }

        // Custom dates
        $testable->set('startDate', '2026-01-01')
            ->set('endDate', '2026-01-31')
            ->assertSuccessful();
    }

    public function test_periodic_report_sorting_across_all_columns(): void
    {
        // Create sample orders for periodic data
        $order = Order::create([
            'order_number' => 'ORD-ADV-SORT-1',
            'branch_id' => $this->branch1->id,
            'cashier_id' => $this->cashier1->id,
            'order_type' => 'dine_in',
            'subtotal' => 200.00,
            'discount_amount' => 20.00,
            'tax_amount' => 21.43,
            'total_amount' => 180.00,
            'status' => OrderStatus::COMPLETED->value,
            'payment_status' => PaymentStatus::PAID->value,
            'completed_at' => now(),
            'created_at' => now(),
        ]);

        Payment::create([
            'order_id' => $order->id,
            'branch_id' => $this->branch1->id,
            'cashier_id' => $this->cashier1->id,
            'payment_method' => 'cash',
            'amount' => 180.00,
            'amount_tendered' => 200.00,
            'change_given' => 20.00,
            'status' => 'successful',
        ]);

        $testable = Livewire::actingAs($this->admin)->test(SalesRecords::class);

        $columns = [
            'period_key',
            'orders_count',
            'gross_sales',
            'discount_total',
            'net_sales',
            'vatable_sales',
            'vat_amount',
            'vat_exempt_sales',
            'average_ticket',
            'non_existent_column', // Test resilience to unknown column
        ];

        foreach ($columns as $column) {
            // Toggle asc
            $testable->call('sortPeriodic', $column)
                ->assertSet('periodicSortColumn', $column)
                ->assertSuccessful();

            // Toggle desc
            $testable->call('sortPeriodic', $column)
                ->assertSet('periodicSortColumn', $column)
                ->assertSuccessful();
        }

        // Intervals
        foreach (['day', 'week', 'month', 'year'] as $interval) {
            $testable->set('periodicInterval', $interval)
                ->assertSuccessful();
        }
    }

    public function test_branch_manager_tenancy_cannot_leak_foreign_branch_in_view_or_csv(): void
    {
        // Branch 1 Order
        $order1 = Order::create([
            'order_number' => 'ORD-B1-001',
            'branch_id' => $this->branch1->id,
            'cashier_id' => $this->cashier1->id,
            'order_type' => 'dine_in',
            'subtotal' => 500.00,
            'total_amount' => 500.00,
            'status' => OrderStatus::COMPLETED->value,
            'payment_status' => PaymentStatus::PAID->value,
            'completed_at' => now(),
            'created_at' => now(),
        ]);
        Payment::create([
            'order_id' => $order1->id,
            'branch_id' => $this->branch1->id,
            'cashier_id' => $this->cashier1->id,
            'payment_method' => 'cash',
            'amount' => 500.00,
            'status' => 'successful',
        ]);

        // Branch 2 Order
        $order2 = Order::create([
            'order_number' => 'ORD-B2-001',
            'branch_id' => $this->branch2->id,
            'cashier_id' => $this->cashier2->id,
            'order_type' => 'dine_in',
            'subtotal' => 9999.00,
            'total_amount' => 9999.00,
            'status' => OrderStatus::COMPLETED->value,
            'payment_status' => PaymentStatus::PAID->value,
            'completed_at' => now(),
            'created_at' => now(),
        ]);
        Payment::create([
            'order_id' => $order2->id,
            'branch_id' => $this->branch2->id,
            'cashier_id' => $this->cashier2->id,
            'payment_method' => 'cash',
            'amount' => 9999.00,
            'status' => 'successful',
        ]);

        // Manager 1 tries to tamper branchId to Branch 2
        $testable = Livewire::actingAs($this->manager1)
            ->test(SalesRecords::class)
            ->set('branchId', $this->branch2->id) // Tampering attempt
            ->assertSuccessful();

        $viewData = $testable->instance()->render()->getData();
        // effectiveBranchId forces branch 1
        $this->assertEquals($this->branch1->id, $testable->get('branchId'));
        $this->assertEquals(500.00, $viewData['summary']['gross_sales']);
        $this->assertNotEquals(9999.00, $viewData['summary']['gross_sales']);

        // Tampering cashierId to cashier from Branch 2
        $testable->set('cashierId', $this->cashier2->id);
        $testable->call('$refresh');
        $this->assertNull($testable->get('cashierId'), 'Foreign branch cashierId must be clamped to null');

        // Manager 1 exporting periodic CSV with attempted tampered branch 2
        $component = new SalesRecords();
        $component->branchId = $this->branch2->id; // Tampering
        $component->cashierId = $this->cashier2->id; // Tampering
        $service = app(SalesAggregationService::class);

        $response = $component->exportPeriodicCsv($service);
        $this->assertInstanceOf(\Symfony\Component\HttpFoundation\StreamedResponse::class, $response);

        ob_start();
        $response->sendContent();
        $csvContent = ob_get_clean();

        // Must NOT contain Branch 2 data
        $this->assertStringNotContainsString('9999.00', $csvContent);
        // Must contain Branch 1 data
        $this->assertStringContainsString('500.00', $csvContent);
    }

    public function test_multibyte_staff_name_and_zero_orders_reconciliation_rendering(): void
    {
        // Create an order associated with multibyte staff
        $order = Order::create([
            'order_number' => 'ORD-MB-001',
            'branch_id' => $this->branch1->id,
            'cashier_id' => $this->multibyteCashier->id,
            'order_type' => 'dine_in',
            'subtotal' => 150.00,
            'total_amount' => 150.00,
            'status' => OrderStatus::COMPLETED->value,
            'payment_status' => PaymentStatus::PAID->value,
            'completed_at' => now(),
            'created_at' => now(),
        ]);
        Payment::create([
            'order_id' => $order->id,
            'branch_id' => $this->branch1->id,
            'cashier_id' => $this->multibyteCashier->id,
            'payment_method' => 'cash',
            'amount' => 150.00,
            'status' => 'successful',
        ]);

        // Render SalesRecords and ensure mb_substr initial does not throw or corrupt
        Livewire::actingAs($this->admin)
            ->test(SalesRecords::class)
            ->assertSuccessful()
            ->assertSee('Ñiño 🌟 Staff')
            ->assertSee('Ñ'); // The multibyte initial
    }

    public function test_stream_periodic_csv_handles_empty_stream_and_null_dates(): void
    {
        $service = app(SalesAggregationService::class);
        $stream = fopen('php://memory', 'r+');

        $service->streamPeriodicCsv(
            $stream,
            'day',
            null,
            null,
            $this->branch1->id,
            null
        );

        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('Period (Day)', $csv);
        $this->assertStringContainsString('TOTAL / SUMMARY', $csv);
    }
}
