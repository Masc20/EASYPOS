<?php

namespace Tests\Feature;

use App\Domains\Identity\Models\User;
use App\Domains\PointOfSale\Models\Order;
use App\Domains\PointOfSale\Models\Payment;
use App\Domains\PointOfSale\Services\SalesAggregationService;
use App\Filament\Pages\SalesRecords;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SalesRecordsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $owner;
    protected User $cashier;
    protected User $cook;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->admin = User::where('email', 'admin@easypos.com')->firstOrFail();
        $this->owner = User::where('email', 'owner@easypos.com')->firstOrFail();
        $this->cashier = User::where('emp_id', 'B01-CSH-001')->firstOrFail();
        $this->cook = User::where('emp_id', 'B01-COK-001')->firstOrFail();
    }

    public function test_admin_can_access_sales_records_page(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/sales-records');
        $response->assertStatus(200);
        $response->assertSee('Sales Records & Financial Summaries');
    }

    public function test_owner_can_access_sales_records_page(): void
    {
        $responseOwner = $this->actingAs($this->owner)->get('/admin/sales-records');
        $responseOwner->assertStatus(200);
    }

    public function test_floor_staff_cannot_access_sales_records(): void
    {
        $responseCashier = $this->actingAs($this->cashier)->get('/admin/sales-records');
        $responseCashier->assertStatus(403);

        $responseCook = $this->actingAs($this->cook)->get('/admin/sales-records');
        $responseCook->assertStatus(403);
    }

    public function test_sales_aggregation_service_computes_accurate_financials(): void
    {
        // Order 1: Cash payment (₱500 subtotal, ₱100 discount, ₱400 total)
        $order1 = Order::create([
            'order_number' => 'ORD-TEST-001',
            'branch_id' => 1,
            'cashier_id' => $this->cashier->id,
            'order_type' => 'dine_in',
            'subtotal' => 500.00,
            'discount_amount' => 100.00,
            'tax_amount' => 0.00,
            'total_amount' => 400.00,
            'status' => 'completed',
            'payment_status' => 'paid',
            'completed_at' => now(),
        ]);

        Payment::create([
            'order_id' => $order1->id,
            'branch_id' => 1,
            'cashier_id' => $this->cashier->id,
            'payment_method' => 'cash',
            'amount' => 400.00,
            'amount_tendered' => 500.00,
            'change_given' => 100.00,
            'status' => 'successful',
        ]);

        // Order 2: GCash QR payment (₱600 subtotal, ₱0 discount, ₱600 total)
        $order2 = Order::create([
            'order_number' => 'ORD-TEST-002',
            'branch_id' => 1,
            'cashier_id' => $this->cashier->id,
            'order_type' => 'takeout',
            'subtotal' => 600.00,
            'discount_amount' => 0.00,
            'tax_amount' => 0.00,
            'total_amount' => 600.00,
            'status' => 'completed',
            'payment_status' => 'paid',
            'completed_at' => now(),
        ]);

        Payment::create([
            'order_id' => $order2->id,
            'branch_id' => 1,
            'cashier_id' => $this->cashier->id,
            'payment_method' => 'qr_transfer',
            'amount' => 600.00,
            'amount_tendered' => 600.00,
            'change_given' => 0.00,
            'status' => 'successful',
        ]);

        $service = app(SalesAggregationService::class);
        $summary = $service->getSummary();

        $this->assertEquals(1100.00, $summary['gross_sales']);
        $this->assertEquals(1000.00, $summary['net_sales']);
        $this->assertEquals(100.00, $summary['total_discounts']);
        $this->assertEquals(2, $summary['orders_count']);
        $this->assertEquals(500.00, $summary['average_order_value']);

        // Check payment method breakdown
        $cashPayment = collect($summary['payments_breakdown'])->firstWhere('method', 'cash');
        $this->assertNotNull($cashPayment);
        $this->assertEquals(400.00, $cashPayment['total']);
        $this->assertEquals(40.0, $cashPayment['percentage']);

        $qrPayment = collect($summary['payments_breakdown'])->firstWhere('method', 'qr_transfer');
        $this->assertNotNull($qrPayment);
        $this->assertEquals(600.00, $qrPayment['total']);
        $this->assertEquals(60.0, $qrPayment['percentage']);
    }

    public function test_sales_aggregation_csv_export_format(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-CSV-001',
            'branch_id' => 1,
            'cashier_id' => $this->cashier->id,
            'order_type' => 'dine_in',
            'subtotal' => 350.00,
            'discount_amount' => 50.00,
            'tax_amount' => 0.00,
            'total_amount' => 300.00,
            'status' => 'completed',
            'payment_status' => 'paid',
            'completed_at' => now(),
        ]);

        Payment::create([
            'order_id' => $order->id,
            'branch_id' => 1,
            'cashier_id' => $this->cashier->id,
            'payment_method' => 'cash',
            'amount' => 300.00,
            'amount_tendered' => 500.00,
            'change_given' => 200.00,
            'status' => 'successful',
        ]);

        $service = app(SalesAggregationService::class);
        $csv = $service->exportCsv();

        $this->assertStringContainsString('Order #', $csv);
        $this->assertStringContainsString('Gross Subtotal (PHP)', $csv);
        $this->assertStringContainsString('Net Total (PHP)', $csv);
        $this->assertStringContainsString('ORD-CSV-001', $csv);
        $this->assertStringContainsString('300.00', $csv);
    }

    public function test_sales_records_livewire_component_renders_and_filters(): void
    {
        Livewire::actingAs($this->admin)
            ->test(SalesRecords::class)
            ->assertSee('Gross Sales')
            ->assertSee('Net Revenue')
            ->call('setPreset', 'yesterday')
            ->assertSet('selectedPreset', 'yesterday');
    }
}
