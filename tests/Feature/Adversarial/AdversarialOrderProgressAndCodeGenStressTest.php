<?php

namespace Tests\Feature\Adversarial;

use App\Domains\Branches\Models\Branch;
use App\Domains\Identity\Models\User;
use App\Domains\Kitchen\Models\KitchenTicket;
use App\Domains\Kitchen\Models\KitchenTicketItem;
use App\Domains\Menu\Models\Meal;
use App\Domains\Menu\Models\MealCategory;
use App\Domains\PointOfSale\Actions\CreateOrderAction;
use App\Domains\PointOfSale\Actions\ProcessPaymentAction;
use App\Domains\PointOfSale\Actions\VoidOrderAction;
use App\Domains\PointOfSale\Livewire\OrderProgress;
use App\Domains\PointOfSale\Models\Order;
use App\Domains\PointOfSale\Models\OrderItem;
use App\Support\Enums\KitchenTicketStatus;
use App\Support\Enums\OrderStatus;
use App\Support\Enums\PaymentStatus;
use App\Support\Enums\StaffRole;
use App\Support\Services\CodeGeneratorService;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class AdversarialOrderProgressAndCodeGenStressTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branch1;
    protected Branch $branch2;
    protected User $cashierB1;
    protected User $cashierB2;
    protected User $managerB1;
    protected MealCategory $dessertsCategory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(MenuSeeder::class);

        $this->branch1 = Branch::where('code', 'B01')->firstOrFail();
        $this->branch2 = Branch::where('code', 'B02')->firstOrFail();

        $this->cashierB1 = User::where('emp_id', 'B01-CSH-001')->firstOrFail();
        $this->cashierB2 = User::where('emp_id', 'B02-CSH-001')->firstOrFail();
        $this->managerB1 = User::where('emp_id', 'B01-MGR-001')->firstOrFail();

        $this->dessertsCategory = MealCategory::firstOrCreate(['slug' => 'desserts'], [
            'name' => 'Desserts',
            'is_active' => true,
        ]);
    }

    protected function createPaidTicket(User $cashier, string $table = '5', string $customer = 'Stress Guest'): KitchenTicket
    {
        $meal = Meal::where('branch_id', $cashier->branch_id)->first() ?? Meal::firstOrFail();

        $order = app(CreateOrderAction::class)->execute([
            'cashier_id' => $cashier->id,
            'branch_id' => $cashier->branch_id ?? 1,
            'order_type' => 'dine_in',
            'table_number' => $table,
            'customer_name' => $customer,
            'items' => [
                [
                    'meal_id' => $meal->id,
                    'quantity' => 1,
                    'unit_price' => (float) $meal->price,
                ],
            ],
        ]);

        app(ProcessPaymentAction::class)->execute($order, [
            'payment_method' => 'cash',
            'amount_tendered' => (float) $order->total_amount + 50.00,
            'cashier_id' => $cashier->id,
        ]);

        return KitchenTicket::where('order_id', $order->id)->firstOrFail();
    }

    public function test_order_progress_closed_drawer_triggers_skip_render_and_suppresses_expensive_queries(): void
    {
        // Seed 10 tickets for branch 1
        for ($i = 1; $i <= 10; $i++) {
            $this->createPaidTicket($this->cashierB1, (string) $i, "Guest {$i}");
        }

        // Mount component with default closed state
        $component = Livewire::actingAs($this->cashierB1)->test(OrderProgress::class);
        $component->assertSet('isOpen', false);

        DB::flushQueryLog();
        DB::enableQueryLog();

        // Dispatch order-created event while closed
        $component->dispatch('order-created');

        // Verify skipRender was triggered on component instance
        $isSkipped = \Livewire\store($component->instance())->get('skipRender');
        $this->assertTrue((bool) $isSkipped, 'Drawer closed must trigger skipRender() on order lifecycle event.');

        // Tickets collection must remain completely empty (deferred)
        $this->assertCount(0, $component->viewData('tickets'), 'Tickets list must remain empty when drawer is closed.');

        // Verify query log: NO queries to order_items, kitchen_ticket_items, or users
        $queries = collect(DB::getQueryLog())->pluck('query')->join(' ');
        $this->assertStringNotContainsString('order_items', $queries, 'Must not query order_items when closed.');
        $this->assertStringNotContainsString('kitchen_ticket_items', $queries, 'Must not query kitchen_ticket_items when closed.');

        // Only 1 grouped count query should run
        $ticketQueries = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'kitchen_tickets'));
        $this->assertCount(1, $ticketQueries, 'At most 1 badge count aggregate query should run on kitchen_tickets.');
    }

    public function test_order_progress_closed_drawer_event_flurry_consistently_skips_render(): void
    {
        $component = Livewire::actingAs($this->cashierB1)->test(OrderProgress::class);
        $component->assertSet('isOpen', false);

        $events = [
            'kitchen-ticket-status-updated',
            'kitchen-ticket-claimed',
            'kitchen-ticket-item-status-updated',
            'payment-processed',
            'order-created',
            'order-voided',
        ];

        foreach ($events as $event) {
            $component->dispatch($event);
            $isSkipped = \Livewire\store($component->instance())->get('skipRender');
            $this->assertTrue((bool) $isSkipped, "Event '{$event}' must trigger skipRender() when drawer is closed.");
            $this->assertCount(0, $component->viewData('tickets'), "Tickets must remain empty on '{$event}' when closed.");
        }
    }

    public function test_order_progress_cache_invalidation_lifecycle_clears_stale_and_poisoned_cache(): void
    {
        $branchId = $this->cashierB1->branch_id ?? 1;
        $cacheKey = "branch_{$branchId}_order_progress_counts";

        // Seed 3 paid tickets
        $this->createPaidTicket($this->cashierB1, '1');
        $this->createPaidTicket($this->cashierB1, '2');
        $this->createPaidTicket($this->cashierB1, '3');

        $component = Livewire::actingAs($this->cashierB1)->test(OrderProgress::class);
        $this->assertEquals(3, $component->get('counts')['all']);

        // 1. Invalidate via 'order-created' after cache tampering
        Cache::put($cacheKey, ['all' => 999, 'new' => 999, 'in_prep' => 0, 'ready' => 0, 'served' => 0], 60);
        $this->assertEquals(999, Cache::get($cacheKey)['all']);

        $component->dispatch('order-created');
        $counts = $component->get('counts');
        $this->assertEquals(3, $counts['all'], 'order-created must bust poisoned cache and restore actual count.');
        $this->assertEquals(3, Cache::get($cacheKey)['all']);

        // 2. Invalidate via 'kitchen-ticket-claimed'
        Cache::put($cacheKey, ['all' => 777, 'new' => 777, 'in_prep' => 0, 'ready' => 0, 'served' => 0], 60);
        $component->dispatch('kitchen-ticket-claimed');
        $this->assertEquals(3, $component->get('counts')['all'], 'kitchen-ticket-claimed must bust cache.');

        // 3. Invalidate via 'order-voided'
        Cache::put($cacheKey, ['all' => 555, 'new' => 555, 'in_prep' => 0, 'ready' => 0, 'served' => 0], 60);
        $component->dispatch('order-voided');
        $this->assertEquals(3, $component->get('counts')['all'], 'order-voided must bust cache.');
    }

    public function test_order_progress_void_isolation_removes_ticket_from_counts_and_queries_upon_drawer_open(): void
    {
        $ticket = $this->createPaidTicket($this->cashierB1, '15', 'To Void Guest');

        // Mount and open drawer
        $component = Livewire::actingAs($this->cashierB1)->test(OrderProgress::class)->call('open');
        $this->assertEquals(1, $component->get('counts')['all']);
        $this->assertCount(1, $component->viewData('tickets'));

        // Void the order via VoidOrderAction
        app(VoidOrderAction::class)->execute([
            'order_id' => $ticket->order_id,
            'manager_id' => $this->managerB1->emp_id,
            'manager_pin' => '9999',
            'reason' => 'Customer changed mind before preparation',
        ]);

        // Component receives order-voided event
        $component->dispatch('order-voided');
        $this->assertEquals(0, $component->get('counts')['all'], 'Counts must be 0 after void.');

        // Fresh component mount and open must reflect 0 tickets and 0 counts
        $freshComponent = Livewire::actingAs($this->cashierB1)->test(OrderProgress::class)->call('open');
        $this->assertEquals(0, $freshComponent->get('counts')['all']);
        $this->assertCount(0, $freshComponent->viewData('tickets'), 'Ticket list must be empty after void.');

        // Adversarial check: order status cancelled or refunded with un-cancelled ticket row is still excluded
        $tamperedOrder = Order::create([
            'branch_id' => $this->branch1->id,
            'cashier_id' => $this->cashierB1->id,
            'order_number' => 'ORD-TAMPERED-001',
            'order_type' => 'dine_in',
            'total_amount' => 100.00,
            'status' => OrderStatus::CANCELLED->value,
            'payment_status' => PaymentStatus::REFUNDED->value,
        ]);

        KitchenTicket::create([
            'branch_id' => $this->branch1->id,
            'order_id' => $tamperedOrder->id,
            'ticket_number' => 'K-TAMPERED-001',
            'status' => KitchenTicketStatus::NEW->value, // Ticket status un-synced
        ]);

        $tamperedComponent = Livewire::actingAs($this->cashierB1)->test(OrderProgress::class)->call('open');
        $this->assertEquals(0, $tamperedComponent->get('counts')['all'], 'Cancelled/refunded orders must remain strictly excluded.');
        $this->assertCount(0, $tamperedComponent->viewData('tickets'));
    }

    public function test_code_generator_emp_id_concurrency_and_collision_avoidance(): void
    {
        $branchId = $this->branch1->id;

        // Pre-create sequential cashiers 002 through 005
        for ($i = 2; $i <= 5; $i++) {
            User::create([
                'name' => "Pre-existing Cashier {$i}",
                'emp_id' => "B01-CSH-00{$i}",
                'branch_id' => $branchId,
                'pin_code' => Hash::make('1234'),
                'is_active' => true,
            ]);
        }

        // Expected next is 006
        $id1 = CodeGeneratorService::generateEmpId($branchId, StaffRole::CASHIER->value);
        $this->assertEquals('B01-CSH-006', $id1);

        // Simulate concurrent collision: another process takes 006
        User::create([
            'name' => 'Concurrent Cashier 6',
            'emp_id' => 'B01-CSH-006',
            'branch_id' => $branchId,
            'pin_code' => Hash::make('1234'),
            'is_active' => true,
        ]);

        // Collision avoidance loop must detect 006 exists and advance to 007
        $id2 = CodeGeneratorService::generateEmpId($branchId, StaffRole::CASHIER->value);
        $this->assertEquals('B01-CSH-007', $id2);

        // Insertion must succeed without unique constraint failure
        $newUser = User::create([
            'name' => 'New Cashier 7',
            'emp_id' => $id2,
            'branch_id' => $branchId,
            'pin_code' => Hash::make('1234'),
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('users', ['emp_id' => 'B01-CSH-007']);

        // HQ prefix generation
        $hqId = CodeGeneratorService::generateEmpId(null, StaffRole::OWNER->value);
        $this->assertEquals('HQ-ADM-001', $hqId);
    }

    public function test_code_generator_menu_code_collision_avoidance_and_soft_delete_preservation(): void
    {
        $catId = $this->dessertsCategory->id;

        // 1. Create meals D-001, D-002, D-003
        $meal1 = Meal::create([
            'category_id' => $catId,
            'name' => 'Dessert 1',
            'menu_code' => 'D-001',
            'price' => 100.00,
        ]);
        $meal2 = Meal::create([
            'category_id' => $catId,
            'name' => 'Dessert 2',
            'menu_code' => 'D-002',
            'price' => 100.00,
        ]);
        $meal3 = Meal::create([
            'category_id' => $catId,
            'name' => 'Dessert 3',
            'menu_code' => 'D-003',
            'price' => 100.00,
        ]);

        // 2. Soft-delete D-002 and D-003
        $meal2->delete();
        $meal3->delete();
        $this->assertTrue($meal2->trashed());
        $this->assertTrue($meal3->trashed());

        // 3. Next code must be D-004 (never re-use soft-deleted D-002 or D-003)
        $nextCode = CodeGeneratorService::generateMenuCode($catId);
        $this->assertEquals('D-004', $nextCode, 'Must not reuse soft-deleted menu codes.');

        // 4. Simulate collision: someone manually created D-004 and soft-deleted it
        $meal4 = Meal::create([
            'category_id' => $catId,
            'name' => 'Dessert 4',
            'menu_code' => 'D-004',
            'price' => 100.00,
        ]);
        $meal4->delete();

        // 5. Collision avoidance loop must advance past soft-deleted D-004 to D-005
        $nextCodeAfterCollision = CodeGeneratorService::generateMenuCode($catId);
        $this->assertEquals('D-005', $nextCodeAfterCollision, 'Collision loop must advance past soft-deleted records.');

        // 6. Null category fallback with soft delete
        $defaultCode1 = CodeGeneratorService::generateMenuCode(null);
        $this->assertEquals('ITM-001', $defaultCode1);

        $defaultMeal = Meal::create([
            'category_id' => $catId,
            'name' => 'Default Item 1',
            'menu_code' => 'ITM-001',
            'price' => 50.00,
        ]);
        $defaultMeal->delete();

        $defaultCode2 = CodeGeneratorService::generateMenuCode(null);
        $this->assertEquals('ITM-002', $defaultCode2, 'Null category fallback must respect soft deletes.');
    }

    public function test_code_generator_branch_code_collision_avoidance_and_fallbacks(): void
    {
        // Name produces acronym
        $code1 = CodeGeneratorService::generateBranchCode('Makati Central');
        $this->assertEquals('MACE', $code1);

        // Pre-create branch with MACE
        Branch::create(['name' => 'Makati Central', 'code' => 'MACE', 'is_active' => true]);

        // Pre-create sequential branches B03 and B04 (B01 and B02 exist from seeder)
        Branch::create(['name' => 'Branch Three', 'code' => 'B03', 'is_active' => true]);
        Branch::create(['name' => 'Branch Four', 'code' => 'B04', 'is_active' => true]);

        // Second branch with same acronym falls back to sequential, skipping B01..B04 to B05
        $fallbackCode = CodeGeneratorService::generateBranchCode('Makati Central');
        $this->assertEquals('B05', $fallbackCode, 'Sequential branch fallback must skip existing B{NN} codes.');
    }
}
