<?php

namespace Tests\Feature\Security;

use App\Domains\Identity\Models\User;
use App\Domains\Kitchen\Livewire\KitchenDisplay;
use App\Domains\Menu\Actions\CreateMealAction;
use App\Domains\Menu\Actions\DeleteMealAction;
use App\Domains\Menu\Actions\ToggleMealAvailabilityAction;
use App\Domains\Menu\Actions\UpdateBranchPortionsAction;
use App\Domains\Menu\Actions\UpdateMealAction;
use App\Domains\Menu\Livewire\MealCatalogTable;
use App\Domains\Menu\Models\Meal;
use App\Domains\Menu\Models\MealCategory;
use App\Domains\PointOfSale\Actions\VoidOrderAction;
use App\Domains\PointOfSale\Actions\CreateOrderAction;
use App\Domains\PointOfSale\Livewire\Checkout\CheckoutModal;
use App\Domains\PointOfSale\Livewire\Checkout\ReceiptPreview;
use App\Domains\PointOfSale\Livewire\MealCatalog;
use App\Domains\PointOfSale\Livewire\OrderProgress;
use App\Domains\PointOfSale\Models\Order;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class RbacFloorRevocationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public static function executiveRoles(): array
    {
        return [['owner'], ['super-admin']];
    }

    private function executiveWithout(string $role, array $permissions): User
    {
        $user = User::role($role)->firstOrFail();
        Role::findByName($role)->revokePermissionTo($permissions);
        $this->actingAs($user->fresh());

        return Auth::user();
    }

    #[DataProvider('executiveRoles')]
    public function test_executive_menu_permission_revocation_blocks_every_domain_write(string $role): void
    {
        $this->executiveWithout($role, ['menu.manage']);
        $meal = $this->meal();
        $before = $meal->fresh()->getAttributes();
        $count = Meal::count();
        $operations = [
            fn () => app(CreateMealAction::class)->handle([]),
            fn () => app(UpdateMealAction::class)->handle($meal, ['name' => 'Forbidden']),
            fn () => app(DeleteMealAction::class)->handle($meal),
            fn () => app(ToggleMealAvailabilityAction::class)->handle($meal),
            fn () => app(UpdateBranchPortionsAction::class)->handle($meal->id, true, 3),
        ];
        foreach ($operations as $operation) {
            try {
                $operation();
                $this->fail('Menu writes must require menu.manage even for executive roles.');
            } catch (HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
            }
            $this->assertSame($count, Meal::count());
            $this->assertSame($before, $meal->fresh()->getAttributes());
        }
    }

    #[DataProvider('executiveRoles')]
    public function test_executive_cannot_read_receipts_after_all_receipt_permissions_are_revoked(string $role): void
    {
        $user = User::role($role)->firstOrFail();
        $component = Livewire::actingAs($user)->test(ReceiptPreview::class)->assertOk();
        $this->executiveWithout($role, ['pos.access', 'reports.view']);
        $order = $this->order(Auth::user());
        $component->call('loadOrder', $order->id)->assertForbidden();
    }

    private function meal(): Meal
    {
        $category = MealCategory::create(['name' => 'RBAC meals', 'slug' => 'rbac-meals', 'is_active' => true]);

        return Meal::create([
            'name' => 'RBAC meal', 'menu_code' => 'RBAC-001', 'category_id' => $category->id,
            'price' => 100, 'is_active' => true, 'is_available' => true, 'track_stock' => true, 'stock_quantity' => 5,
        ]);
    }

    private function order(User $user): Order
    {
        return Order::create([
            'order_number' => 'RBAC-ORDER', 'branch_id' => 1, 'cashier_id' => $user->id,
            'order_type' => 'dine_in', 'subtotal' => 100, 'discount_amount' => 0,
            'tax_amount' => 0, 'total_amount' => 100, 'status' => 'completed', 'payment_status' => 'paid',
        ]);
    }

    #[DataProvider('executiveRoles')]
    public function test_executive_void_requires_current_refund_permission(string $role): void
    {
        $user = $this->executiveWithout($role, ['pos.refund']);
        $order = Order::create([
            'order_number' => 'REVOKED-REFUND', 'branch_id' => 1, 'cashier_id' => $user->id,
            'order_type' => 'dine_in', 'subtotal' => 100, 'discount_amount' => 0,
            'tax_amount' => 0, 'total_amount' => 100, 'status' => 'completed', 'payment_status' => 'paid',
        ]);
        try {
            app(VoidOrderAction::class)->execute([
                'order_id' => $order->id, 'manager_id' => $user->id,
                'skip_pin_check' => true, 'reason' => 'Must be rejected',
            ]);
            $this->fail('Voiding requires the approver current pos.refund permission.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertSame('Unauthorized back-office void attempt.', $exception->getMessage());
        }
        $this->assertSame('completed', $order->fresh()->status);
        $this->assertSame('paid', $order->fresh()->payment_status);
    }

    public function test_mounted_pos_catalog_rejects_permission_revocation(): void
    {
        $cashier = User::where('emp_id', 'B01-CSH-001')->firstOrFail();
        $component = Livewire::actingAs($cashier)->test(MealCatalog::class)->assertOk();
        Role::findByName('cashier')->revokePermissionTo('pos.access');
        Auth::setUser($cashier->fresh());
        $component->call('refreshCatalog')->assertForbidden();
    }

    public function test_mounted_menu_rejects_view_permission_revocation_even_when_manage_remains(): void
    {
        $chef = User::where('emp_id', 'B01-CHF-001')->firstOrFail();
        $component = Livewire::actingAs($chef)->test(MealCatalogTable::class)->assertOk();
        Role::findByName('chef')->revokePermissionTo('menu.view');
        Auth::setUser($chef->fresh());
        $component->call('refreshCatalog')->assertForbidden();
    }

    public function test_checkout_denies_checkout_only_actor_without_station_access(): void
    {
        $cashier = User::where('emp_id', 'B01-CSH-001')->firstOrFail();
        $component = Livewire::actingAs($cashier)->test(CheckoutModal::class);
        Role::findByName('cashier')->revokePermissionTo('pos.access');
        Auth::setUser($cashier->fresh());
        $component->call('processCheckout')->assertForbidden();
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_order_progress_cannot_select_another_branch_through_public_count_action(): void
    {
        $cashier = User::where('emp_id', 'B01-CSH-001')->firstOrFail();
        $this->actingAs($cashier);
        $component = app(OrderProgress::class);
        $query = $component->buildTicketQuery(999);
        $this->assertContains($cashier->branch_id, $query->getBindings());
        $this->assertNotContains(999, $query->getBindings());
        $this->assertSame($component->loadBadgeCounts(), $component->loadBadgeCounts(999));
    }

    public function test_kitchen_view_revocation_denies_mounted_display_even_with_prepare_granted(): void
    {
        $cook = User::where('emp_id', 'B01-COK-001')->firstOrFail();
        $component = Livewire::actingAs($cook)->test(KitchenDisplay::class);
        Role::findByName('cook')->revokePermissionTo('kitchen.view');
        Auth::setUser($cook->fresh());
        $component->call('pollUpdates')->assertForbidden();
    }

    #[DataProvider('executiveRoles')]
    public function test_custom_discount_requires_explicit_permission_and_rolls_back_stock_changes(string $role): void
    {
        $user = $this->executiveWithout($role, ['pos.discount']);
        $meal = $this->meal();
        try {
            app(CreateOrderAction::class)->execute([
                'branch_id' => 1, 'cashier_id' => $user->id, 'order_type' => 'dine_in',
                'discount_type' => 'custom', 'custom_discount' => 10,
                'items' => [['meal_id' => $meal->id, 'quantity' => 1]],
            ]);
            $this->fail('Custom discounts require an explicit current permission.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertSame('User is not authorized to apply custom discounts.', $exception->getMessage());
        }
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertSame(5, $meal->fresh()->stock_quantity);
    }
}
