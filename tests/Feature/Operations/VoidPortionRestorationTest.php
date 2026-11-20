<?php

namespace Tests\Feature\Operations;

use App\Domains\Identity\Models\User;
use App\Domains\Kitchen\Actions\RecallKitchenTicketAction;
use App\Domains\Kitchen\Actions\UpdateKitchenTicketStatusAction;
use App\Domains\Menu\Models\Meal;
use App\Domains\Menu\Models\MealCategory;
use App\Domains\PointOfSale\Actions\CreateOrderAction;
use App\Domains\PointOfSale\Actions\ProcessPaymentAction;
use App\Domains\PointOfSale\Actions\VoidOrderAction;
use App\Domains\PointOfSale\Livewire\MealCatalog;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class VoidPortionRestorationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->actingAs(User::where('emp_id', 'B01-CSH-001')->firstOrFail());
    }

    public static function unservedOrders(): array
    {
        return [
            'branch new' => [false, 'new'], 'branch preparing' => [false, 'in_prep'], 'branch ready' => [false, 'ready'],
            'shared new' => [true, 'new'], 'shared preparing' => [true, 'in_prep'], 'shared ready' => [true, 'ready'],
        ];
    }

    private function checkout(bool $shared, int $stock = 1, bool $tracked = true): array
    {
        $category = MealCategory::create(['name' => 'Void test', 'slug' => 'void-test', 'is_active' => true]);
        $meal = Meal::create([
            'name' => 'Last portion', 'menu_code' => 'VOID-LAST', 'category_id' => $category->id,
            'branch_id' => $shared ? null : 1, 'price' => 100, 'is_active' => true,
            'is_available' => true, 'track_stock' => $tracked, 'stock_quantity' => $stock,
        ]);
        $order = app(CreateOrderAction::class)->execute([
            'branch_id' => 1, 'cashier_id' => auth()->id(), 'order_type' => 'takeout',
            'items' => [['meal_id' => $meal->id, 'quantity' => 1]],
        ]);
        $paid = app(ProcessPaymentAction::class)->execute($order, [
            'cashier_id' => auth()->id(), 'payment_method' => 'cash', 'amount_tendered' => 100,
        ]);
        return [$meal->fresh(), $paid['order'], $paid['kitchen_ticket']];
    }

    private function void($order): void
    {
        app(VoidOrderAction::class)->execute([
            'order_id' => $order->id, 'manager_id' => 'B01-MGR-001', 'manager_pin' => '9999', 'reason' => 'Customer cancelled',
        ]);
    }

    #[DataProvider('unservedOrders')]
    public function test_void_returns_last_portion_and_reopens_catalog_only_for_its_branch(bool $shared, string $state): void
    {
        [$meal, $order, $ticket] = $this->checkout($shared);
        $ticket->update(['status' => $state]);
        $this->assertSame(0, $meal->stockQuantityForBranch(1));
        $this->assertTrue($meal->isSoldOutForBranch(1));
        $catalog = Livewire::test(MealCatalog::class);
        $this->void($order);
        $fresh = $meal->fresh();
        $this->assertSame(1, $fresh->stockQuantityForBranch(1));
        $this->assertFalse($fresh->isSoldOutForBranch(1));
        if ($shared) {
            $this->assertSame(1, $fresh->stock_quantity);
            $this->assertSame(1, $fresh->stockQuantityForBranch(2));
            $this->assertTrue($fresh->is_available);
        }
        $catalog->call('syncAvailability')->assertDispatched('meal-availability-synced')
            ->assertViewHas('meals', fn ($meals) => ! $meals->firstWhere('id', $meal->id)->isSoldOutForBranch(1));
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame('cancelled', $ticket->fresh()->status);
        try {
            $this->void($order);
            $this->fail('Repeated void must not restore twice.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertStringContainsString('already cancelled', $exception->getMessage());
        }
        $this->assertSame(1, $meal->fresh()->stockQuantityForBranch(1));
    }

    public function test_served_portions_stay_consumed_even_after_a_ticket_is_recalled(): void
    {
        [$meal, $order, $ticket] = $this->checkout(true);
        $this->actingAs(User::where('emp_id', 'B01-COK-001')->firstOrFail());
        $ticket = app(UpdateKitchenTicketStatusAction::class)->execute($ticket, 'served');
        $this->assertSame('completed', $order->fresh()->status);
        app(RecallKitchenTicketAction::class)->execute($ticket);
        $this->assertSame('in_kitchen', $order->fresh()->status);
        $this->actingAs(User::where('emp_id', 'B01-CSH-001')->firstOrFail());
        $this->void($order);
        $this->assertSame(0, $meal->fresh()->stockQuantityForBranch(1));
        $this->assertTrue($meal->fresh()->isSoldOutForBranch(1));
    }

    public function test_manual_86_with_positive_stock_is_preserved(): void
    {
        [$meal, $order] = $this->checkout(false, 3);
        $meal->update(['is_available' => false]);
        $this->void($order);
        $this->assertSame(3, $meal->fresh()->stock_quantity);
        $this->assertFalse($meal->fresh()->is_available);
    }

    public function test_historical_order_without_stock_snapshot_restores_the_same_branch(): void
    {
        [$meal, $order] = $this->checkout(true);
        $order->items()->update(['stock_source' => null]);
        $this->void($order);
        $this->assertSame(1, $meal->fresh()->stockQuantityForBranch(1));
        $this->assertFalse($meal->fresh()->isSoldOutForBranch(1));
        $this->assertSame(1, $meal->fresh()->stockQuantityForBranch(2));
    }

    public function test_original_stock_source_is_restored_after_tracking_configuration_changes(): void
    {
        [$meal, $order] = $this->checkout(true);
        $meal->branchAvailabilities()->where('branch_id', 1)->update(['track_stock' => false]);
        $this->void($order);
        $override = $meal->branchAvailabilities()->where('branch_id', 1)->firstOrFail();
        $this->assertSame(1, $override->stock_quantity);
        $this->assertFalse($override->track_stock);
        $this->assertSame(1, $meal->fresh()->stock_quantity);
    }

    public function test_served_order_void_preserves_consumed_portions(): void
    {
        [$meal, $order, $ticket] = $this->checkout(false);
        $this->actingAs(User::where('emp_id', 'B01-COK-001')->firstOrFail());
        app(UpdateKitchenTicketStatusAction::class)->execute($ticket, 'served');
        $this->actingAs(User::where('emp_id', 'B01-CSH-001')->firstOrFail());
        $this->void($order);
        $this->assertSame(0, $meal->fresh()->stock_quantity);
        $this->assertTrue($meal->fresh()->isSoldOut());
        $this->assertSame('refunded', $order->fresh()->payment_status);
    }

    public function test_wrong_pin_leaves_portions_order_and_payment_unchanged(): void
    {
        [$meal, $order] = $this->checkout(true);
        try {
            app(VoidOrderAction::class)->execute([
                'order_id' => $order->id, 'manager_id' => 'B01-MGR-001', 'manager_pin' => '0000', 'reason' => 'Denied',
            ]);
            $this->fail('Wrong PIN must deny the void.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertSame('Invalid manager PIN.', $exception->getMessage());
        }
        $this->assertSame(0, $meal->fresh()->stockQuantityForBranch(1));
        $this->assertSame('in_kitchen', $order->fresh()->status);
        $this->assertSame('paid', $order->fresh()->payment_status);
    }

    public function test_void_failure_rolls_back_stock_and_refund_writes(): void
    {
        [$meal, $order, $ticket] = $this->checkout(false);
        \Illuminate\Support\Facades\Event::listen(\App\Domains\PointOfSale\Events\OrderVoided::class, function (): void {
            throw new \RuntimeException('Forced rollback');
        });
        try {
            $this->void($order);
            $this->fail('The simulated failure must roll back the void.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Forced rollback', $exception->getMessage());
        }
        $this->assertSame(0, $meal->fresh()->stock_quantity);
        $this->assertFalse($meal->fresh()->is_available);
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame('in_kitchen', $order->fresh()->status);
        $this->assertSame('new', $ticket->fresh()->status);
    }

    public function test_stale_kitchen_requests_cannot_serve_or_claim_a_voided_order(): void
    {
        [$meal, $order, $ticket] = $this->checkout(false);
        $this->void($order);
        $cook = User::where('emp_id', 'B01-COK-001')->firstOrFail();
        $this->actingAs($cook);
        foreach ([
            fn () => app(UpdateKitchenTicketStatusAction::class)->execute($ticket, 'served'),
            fn () => app(\App\Domains\Kitchen\Actions\ClaimKitchenTicketAction::class)->execute($ticket, $cook),
            fn () => app(RecallKitchenTicketAction::class)->execute($ticket),
            fn () => app(\App\Domains\Kitchen\Actions\ToggleKitchenItemStatusAction::class)->execute($ticket->items()->firstOrFail()),
        ] as $operation) {
            try {
                $operation();
                $this->fail('A cancelled ticket must not be revived.');
            } catch (\InvalidArgumentException $exception) {
                $this->assertStringContainsString('Cancelled orders', $exception->getMessage());
            }
        }
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame(1, $meal->fresh()->stock_quantity);
    }

    public function test_untracked_sale_cannot_invent_stock_when_tracking_is_enabled_later(): void
    {
        [$meal, $order] = $this->checkout(false, 0, false);
        $meal->update(['track_stock' => true, 'stock_quantity' => 0, 'is_available' => false]);
        $this->void($order);
        $this->assertSame(0, $meal->fresh()->stock_quantity);
        $this->assertFalse($meal->fresh()->is_available);
    }

    public function test_migration_preserves_consumption_for_existing_served_orders(): void
    {
        [$meal, $order, $ticket] = $this->checkout(true);
        $order->update(['status' => 'completed', 'completed_at' => now()]);
        $ticket->update(['status' => 'served', 'served_at' => now()]);
        $migration = require base_path('database/migrations/2026_11_20_000001_record_order_stock_provenance.php');
        $migration->down();
        $migration->up();
        $this->assertNotNull($order->fresh()->stock_consumed_at);
        $this->assertNull($order->items()->firstOrFail()->stock_source);
        $this->actingAs(User::where('emp_id', 'B01-COK-001')->firstOrFail());
        app(RecallKitchenTicketAction::class)->execute($ticket->fresh());
        $this->actingAs(User::where('emp_id', 'B01-CSH-001')->firstOrFail());
        $this->void($order);
        $this->assertSame(0, $meal->fresh()->stockQuantityForBranch(1));
    }
}
