<?php

namespace Tests\Feature\Security\Audit2026;

use App\Domains\Branches\Models\Branch;
use App\Domains\Identity\Models\User;
use App\Domains\Menu\Models\Meal;
use App\Domains\Menu\Models\MealCategory;
use App\Domains\PointOfSale\Actions\CreateOrderAction;
use App\Domains\PointOfSale\Actions\VoidOrderAction;
use App\Domains\PointOfSale\Livewire\Checkout\ManagerPinModal;
use App\Domains\PointOfSale\Models\Order;
use App\Domains\PointOfSale\Models\Payment;
use App\Support\Enums\OrderStatus;
use App\Support\Enums\OrderType;
use App\Support\Enums\PaymentMethod;
use App\Support\Enums\PaymentStatus;
use App\Support\Enums\StaffRole;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use InvalidArgumentException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class VoidPinThrottleAndOracleTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch1;

    private Branch $branch2;

    private User $owner;

    private User $manager1;

    private User $manager2;

    private User $cashier1;

    private User $cashier2;

    private Meal $meal1;

    private Meal $meal2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->branch1 = Branch::firstOrCreate(['code' => 'B01'], [
            'name' => 'Audit Branch 1',
            'is_active' => true,
        ]);

        $this->branch2 = Branch::firstOrCreate(['code' => 'B02'], [
            'name' => 'Audit Branch 2',
            'is_active' => true,
        ]);

        $category = MealCategory::firstOrCreate(['slug' => 'audit-mains'], [
            'name' => 'Audit Mains',
            'is_active' => true,
        ]);

        $this->owner = User::firstOrCreate(['email' => 'owner@easypos.com'], [
            'name' => 'System Owner',
            'password' => Hash::make('password'),
            'emp_id' => 'HQ-ADM-001',
            'pin_code' => Hash::make('4321'),
            'is_active' => true,
        ]);
        $this->owner->update(['pin_code' => Hash::make('4321')]);
        if (! $this->owner->hasRole(StaffRole::OWNER->value)) {
            $this->owner->assignRole(StaffRole::OWNER->value);
        }

        $this->manager1 = User::create([
            'name' => 'Branch 1 Manager',
            'email' => 'b1_mgr_audit@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'B01-MGR-A01',
            'pin_code' => Hash::make('9876'),
            'branch_id' => $this->branch1->id,
            'is_active' => true,
        ]);
        $this->manager1->assignRole(StaffRole::BRANCH_MANAGER->value);

        $this->manager2 = User::create([
            'name' => 'Branch 2 Manager',
            'email' => 'b2_mgr_audit@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'B02-MGR-A02',
            'pin_code' => Hash::make('5678'),
            'branch_id' => $this->branch2->id,
            'is_active' => true,
        ]);
        $this->manager2->assignRole(StaffRole::BRANCH_MANAGER->value);

        $this->cashier1 = User::create([
            'name' => 'Branch 1 Cashier',
            'email' => 'b1_csh_audit@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'B01-CSH-A01',
            'pin_code' => Hash::make('1111'),
            'branch_id' => $this->branch1->id,
            'is_active' => true,
        ]);
        $this->cashier1->assignRole(StaffRole::CASHIER->value);

        $this->cashier2 = User::create([
            'name' => 'Branch 2 Cashier',
            'email' => 'b2_csh_audit@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'B02-CSH-A02',
            'pin_code' => Hash::make('2222'),
            'branch_id' => $this->branch2->id,
            'is_active' => true,
        ]);
        $this->cashier2->assignRole(StaffRole::CASHIER->value);

        $this->meal1 = Meal::create([
            'name' => 'Branch 1 Adobo',
            'menu_code' => 'B01-AUD-01',
            'category_id' => $category->id,
            'branch_id' => $this->branch1->id,
            'price' => 200.00,
            'is_available' => true,
        ]);

        $this->meal2 = Meal::create([
            'name' => 'Branch 2 Sinigang',
            'menu_code' => 'B02-AUD-01',
            'category_id' => $category->id,
            'branch_id' => $this->branch2->id,
            'price' => 250.00,
            'is_available' => true,
        ]);

        RateLimiter::clear('void-pin-approval:'.request()->ip().':'.strtoupper($this->manager1->emp_id));
        RateLimiter::clear('void-pin-approval:global:'.strtoupper($this->manager1->emp_id));
    }

    private function createBranchOrder(Branch $branch, User $cashier, Meal $meal): Order
    {
        $order = (new CreateOrderAction)->execute([
            'branch_id' => $branch->id,
            'cashier_id' => $cashier->id,
            'order_type' => OrderType::DINE_IN->value,
            'items' => [
                ['meal_id' => $meal->id, 'quantity' => 1],
            ],
        ]);

        Payment::create([
            'order_id' => $order->id,
            'branch_id' => $branch->id,
            'cashier_id' => $cashier->id,
            'payment_method' => PaymentMethod::CASH->value,
            'amount' => $order->total_amount,
            'amount_tendered' => $order->total_amount,
            'change_given' => 0,
            'status' => 'successful',
        ]);

        $order->update(['payment_status' => PaymentStatus::PAID->value]);

        return $order->fresh();
    }

    public function test_vuln01_rotating_order_id_cannot_bypass_manager_pin_rate_limit(): void
    {
        $orders = [];
        for ($i = 0; $i < 6; $i++) {
            $orders[] = $this->createBranchOrder($this->branch1, $this->cashier1, $this->meal1);
        }

        $component = Livewire::actingAs($this->cashier1)->test(ManagerPinModal::class);

        // 5 failed attempts rotating through order IDs 1..5 for the same manager_id
        for ($i = 0; $i < 5; $i++) {
            $component->call('submitVoidRequest', [
                'order_id' => $orders[$i]->id,
                'manager_id' => $this->manager1->emp_id,
                'manager_pin' => '0000',
                'reason' => 'PIN brute force #'.($i + 1),
            ]);

            $this->assertNotEquals(OrderStatus::CANCELLED->value, $orders[$i]->fresh()->status);
        }

        // 6th attempt uses a brand new order_id AND the valid manager PIN — must still be locked out
        $component->call('submitVoidRequest', [
            'order_id' => $orders[5]->id,
            'manager_id' => $this->manager1->emp_id,
            'manager_pin' => '9876',
            'reason' => 'Attempt after 5 failures on new order',
        ]);

        $errorMessage = (string) $component->get('errorMessage');
        $this->assertStringContainsString('Too many failed approval attempts', $errorMessage);
        $this->assertNotEquals(
            OrderStatus::CANCELLED->value,
            $orders[5]->fresh()->status,
            'Rotating order_id must NOT reset the Manager PIN throttle and allow voiding on the 6th attempt.'
        );
    }

    public function test_vuln01_branch1_cashier_cannot_void_branch2_order_via_manager_pin_modal(): void
    {
        $branch2Order = $this->createBranchOrder($this->branch2, $this->cashier2, $this->meal2);

        $component = Livewire::actingAs($this->cashier1)->test(ManagerPinModal::class);
        $component->call('submitVoidRequest', [
            'order_id' => $branch2Order->id,
            'manager_id' => $this->manager2->emp_id,
            'manager_pin' => '5678',
            'reason' => 'Cross-branch void via modal',
        ]);

        $this->assertNotEmpty($component->get('errorMessage'));
        $this->assertNotEquals(OrderStatus::CANCELLED->value, $branch2Order->fresh()->status);
        $this->assertEquals(PaymentStatus::PAID->value, $branch2Order->fresh()->payment_status);
    }

    public function test_vuln01_same_branch_manager_pin_void_succeeds_for_authorized_branch(): void
    {
        $branch1Order = $this->createBranchOrder($this->branch1, $this->cashier1, $this->meal1);

        $component = Livewire::actingAs($this->cashier1)->test(ManagerPinModal::class);
        $component->call('submitVoidRequest', [
            'order_id' => $branch1Order->id,
            'manager_id' => $this->manager1->emp_id,
            'manager_pin' => '9876',
            'reason' => 'Customer cancelled at counter',
        ]);

        $this->assertNull($component->get('errorMessage'));
        $this->assertEquals(OrderStatus::CANCELLED->value, $branch1Order->fresh()->status);
        $this->assertEquals(PaymentStatus::REFUNDED->value, $branch1Order->fresh()->payment_status);
    }

    public function test_vuln03_skip_pin_check_rejects_unauthenticated_call(): void
    {
        $branch1Order = $this->createBranchOrder($this->branch1, $this->cashier1, $this->meal1);
        Auth::logout();
        $this->assertFalse(Auth::check());

        $action = new VoidOrderAction;
        $threw = false;

        try {
            $action->execute([
                'order_id' => $branch1Order->id,
                'manager_id' => $this->manager1->id,
                'reason' => 'Unauthenticated skip_pin_check call',
                'skip_pin_check' => true,
            ]);
        } catch (InvalidArgumentException|AuthorizationException|HttpException $e) {
            $threw = true;
        }

        $this->assertTrue($threw, 'Unauthenticated VoidOrderAction::execute(skip_pin_check: true) must be rejected.');
        $this->assertNotEquals(OrderStatus::CANCELLED->value, $branch1Order->fresh()->status);
        $this->assertEquals(PaymentStatus::PAID->value, $branch1Order->fresh()->payment_status);
    }

    public function test_vuln03_skip_pin_check_rejects_cross_branch_refund_user(): void
    {
        $branch2Order = $this->createBranchOrder($this->branch2, $this->cashier2, $this->meal2);

        $this->actingAs($this->manager1);
        $action = new VoidOrderAction;
        $threw = false;

        try {
            $action->execute([
                'order_id' => $branch2Order->id,
                'manager_id' => $this->manager1->id,
                'reason' => 'Cross-branch skip_pin_check attack',
                'skip_pin_check' => true,
            ]);
        } catch (InvalidArgumentException|AuthorizationException|HttpException $e) {
            $threw = true;
        }

        $this->assertTrue($threw, 'Branch 1 pos.refund actor must be rejected when targeting Branch 2 order.');
        $this->assertNotEquals(OrderStatus::CANCELLED->value, $branch2Order->fresh()->status);
    }

    public function test_vuln03_skip_pin_check_rejects_audit_spoofing_different_manager_id(): void
    {
        $branch1Order = $this->createBranchOrder($this->branch1, $this->cashier1, $this->meal1);

        $this->actingAs($this->manager1);
        $action = new VoidOrderAction;
        $threw = false;

        try {
            $action->execute([
                'order_id' => $branch1Order->id,
                'manager_id' => $this->owner->id, // Spoofing Owner as approver
                'reason' => 'Audit spoofing attempt',
                'skip_pin_check' => true,
            ]);
        } catch (InvalidArgumentException|AuthorizationException|HttpException $e) {
            $threw = true;
        }

        $this->assertTrue($threw, 'skip_pin_check must reject manager_id !== Auth::id() to prevent audit spoofing.');
        $this->assertNotEquals(OrderStatus::CANCELLED->value, $branch1Order->fresh()->status);
    }

    public function test_vuln03_skip_pin_check_rejects_null_branch_scoped_manager(): void
    {
        $branch1Order = $this->createBranchOrder($this->branch1, $this->cashier1, $this->meal1);

        $nullBranchManager = User::create([
            'name' => 'Null Branch Manager',
            'email' => 'null_mgr_audit@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'B00-MGR-NULL',
            'branch_id' => null,
            'is_active' => true,
        ]);
        $nullBranchManager->assignRole(StaffRole::BRANCH_MANAGER->value);

        $this->actingAs($nullBranchManager);
        $action = new VoidOrderAction;
        $threw = false;

        try {
            $action->execute([
                'order_id' => $branch1Order->id,
                'manager_id' => $nullBranchManager->id,
                'reason' => 'Null branch bypass attempt',
                'skip_pin_check' => true,
            ]);
        } catch (InvalidArgumentException|AuthorizationException|HttpException $e) {
            $threw = true;
        }

        $this->assertTrue($threw, 'Null-branch branch-scoped actor must fail closed on VoidOrderAction.');
        $this->assertNotEquals(OrderStatus::CANCELLED->value, $branch1Order->fresh()->status);
    }

    public function test_vuln06_void_errors_do_not_leak_foreign_order_number_status_or_manager_existence(): void
    {
        $branch1Order = $this->createBranchOrder($this->branch1, $this->cashier1, $this->meal1);
        $branch2CancelledOrder = $this->createBranchOrder($this->branch2, $this->cashier2, $this->meal2);
        $branch2CancelledOrder->update([
            'order_number' => 'B02-SECRET-ORD-9999',
            'status' => OrderStatus::CANCELLED->value,
        ]);

        User::create([
            'name' => 'Inactive Manager',
            'email' => 'inactive_mgr@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'B02-INACT-MGR',
            'pin_code' => Hash::make('8888'),
            'branch_id' => $this->branch2->id,
            'is_active' => false,
        ])->assignRole(StaffRole::BRANCH_MANAGER->value);

        $modal = Livewire::actingAs($this->cashier1)->test(ManagerPinModal::class);

        // 1. Cross-branch cancelled order must NOT leak foreign order_number or cancelled status
        $modal->call('submitVoidRequest', [
            'order_id' => $branch2CancelledOrder->id,
            'manager_id' => $this->manager1->emp_id,
            'manager_pin' => '9876',
            'reason' => 'Probe cross-branch order number',
        ]);
        $foreignOrderError = (string) $modal->get('errorMessage');
        $this->assertNotEmpty($foreignOrderError);
        $this->assertStringNotContainsString('B02-SECRET-ORD-9999', $foreignOrderError);
        $this->assertStringNotContainsString('already cancelled', strtolower($foreignOrderError));

        // 2. Nonexistent order ID must NOT enumerate order existence
        $modal->call('submitVoidRequest', [
            'order_id' => 999999,
            'manager_id' => $this->manager1->emp_id,
            'manager_pin' => '9876',
            'reason' => 'Probe missing order ID',
        ]);
        $missingOrderError = (string) $modal->get('errorMessage');
        $this->assertNotEmpty($missingOrderError);
        $this->assertStringNotContainsString('999999', $missingOrderError);
        $this->assertStringNotContainsString('not found', strtolower($missingOrderError));

        // 3. Unknown manager vs inactive manager vs wrong-branch manager vs wrong PIN must emit uniform error
        $modal->call('submitVoidRequest', [
            'order_id' => $branch1Order->id,
            'manager_id' => 'NONEXISTENT-MGR-999',
            'manager_pin' => '0000',
            'reason' => 'Probe unknown manager',
        ]);
        $unknownMgrError = (string) $modal->get('errorMessage');

        $modal->call('submitVoidRequest', [
            'order_id' => $branch1Order->id,
            'manager_id' => 'B02-INACT-MGR',
            'manager_pin' => '8888',
            'reason' => 'Probe inactive manager',
        ]);
        $inactiveMgrError = (string) $modal->get('errorMessage');

        $modal->call('submitVoidRequest', [
            'order_id' => $branch1Order->id,
            'manager_id' => $this->manager2->emp_id,
            'manager_pin' => '5678',
            'reason' => 'Probe foreign manager',
        ]);
        $foreignMgrError = (string) $modal->get('errorMessage');

        $modal->call('submitVoidRequest', [
            'order_id' => $branch1Order->id,
            'manager_id' => $this->manager1->emp_id,
            'manager_pin' => '0000',
            'reason' => 'Probe wrong PIN',
        ]);
        $wrongPinError = (string) $modal->get('errorMessage');

        $this->assertSame($wrongPinError, $unknownMgrError, 'Unknown manager must not be distinguishable from wrong PIN.');
        $this->assertSame($wrongPinError, $inactiveMgrError, 'Inactive manager must not be distinguishable from wrong PIN.');
        $this->assertSame($wrongPinError, $foreignMgrError, 'Cross-branch manager must not be distinguishable from wrong PIN.');
        $this->assertStringNotContainsString('manager record not found', strtolower($unknownMgrError));
        $this->assertStringNotContainsString('does not belong to this branch', strtolower($foreignMgrError));
    }
}
