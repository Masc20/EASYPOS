<?php

namespace Tests\Feature;

use App\Domains\Identity\Models\User;
use App\Domains\Menu\Models\Meal;
use App\Domains\PointOfSale\Models\Order;
use App\Domains\PointOfSale\Models\OrderItem;
use App\Domains\PointOfSale\Models\Payment;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionHistoryTest extends TestCase
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
        $this->seed(MenuSeeder::class);

        $this->admin = User::where('email', 'admin@easypos.com')->firstOrFail();
        $this->owner = User::where('email', 'owner@easypos.com')->firstOrFail();
        $this->cashier = User::where('emp_id', 'B01-CSH-001')->firstOrFail();
        $this->cook = User::where('emp_id', 'B01-COK-001')->firstOrFail();
    }

    public function test_admin_can_access_transaction_history_in_filament(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/orders');

        $response->assertStatus(200);
        $response->assertSee('Transaction History');
    }

    public function test_owner_can_access_transaction_history_in_filament(): void
    {
        $response = $this->actingAs($this->owner)->get('/admin/orders');

        $response->assertStatus(200);
    }

    public function test_floor_staff_cannot_access_transaction_history_in_filament(): void
    {
        $responseCashier = $this->actingAs($this->cashier)->get('/admin/orders');
        $responseCashier->assertStatus(403);

        $responseCook = $this->actingAs($this->cook)->get('/admin/orders');
        $responseCook->assertStatus(403);
    }

    public function test_transaction_history_displays_orders_and_receipt_data(): void
    {
        $meal = Meal::firstOrFail();

        $order = Order::create([
            'order_number' => 'ORD-TXN-12345',
            'branch_id' => 1,
            'cashier_id' => $this->cashier->id,
            'order_type' => 'dine_in',
            'table_number' => '7',
            'subtotal' => 450.00,
            'discount_amount' => 50.00,
            'tax_amount' => 0.00,
            'total_amount' => 400.00,
            'status' => 'completed',
            'payment_status' => 'paid',
            'completed_at' => now(),
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'meal_id' => $meal->id,
            'meal_name' => $meal->name,
            'quantity' => 1,
            'unit_price' => 450.00,
            'subtotal' => 450.00,
        ]);

        Payment::create([
            'order_id' => $order->id,
            'branch_id' => 1,
            'cashier_id' => $this->cashier->id,
            'payment_method' => 'cash',
            'amount' => 400.00,
            'amount_tendered' => 500.00,
            'change_given' => 100.00,
            'status' => 'successful',
        ]);

        $response = $this->actingAs($this->admin)->get('/admin/orders');

        $response->assertStatus(200);
        $response->assertSee('ORD-TXN-12345');
        $response->assertSee('400.00');

        // Test receipt breakdown view
        $receiptView = view('filament.modals.order-receipt', [
            'order' => $order->load(['items.meal', 'payments', 'cashier', 'branch']),
        ])->render();

        $this->assertStringContainsString('ORD-TXN-12345', $receiptView);
        $this->assertStringContainsString($meal->name, $receiptView);
        $this->assertStringContainsString('400.00', $receiptView);
        $this->assertStringContainsString('Cash', $receiptView);
    }
}
