<?php

namespace Tests\Feature\Security;

use App\Domains\Branches\Models\Branch;
use App\Domains\Identity\Models\User;
use App\Domains\Menu\Models\Meal;
use App\Domains\Menu\Models\MealCategory;
use App\Domains\PointOfSale\Actions\CreateOrderAction;
use App\Domains\PointOfSale\Actions\VoidOrderAction;
use App\Domains\PointOfSale\Livewire\Checkout\ManagerPinModal;
use App\Domains\PointOfSale\Models\Order;
use App\Support\Constants\SystemConstants;
use App\Support\Enums\OrderType;
use App\Support\Enums\StaffRole;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use InvalidArgumentException;
use Tests\TestCase;

class Sec05ManagerPinThrottlingTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branch;
    protected User $manager;
    protected User $cashier;
    protected Meal $meal;
    protected Order $order;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->branch = Branch::firstOrCreate(['code' => 'B01'], [
            'name' => 'Pin Security Branch',
            'is_active' => true,
        ]);

        $category = MealCategory::firstOrCreate(['slug' => 'appetizers'], [
            'name' => 'Appetizers',
            'is_active' => true,
        ]);

        $this->manager = User::create([
            'name' => 'Manager Pin Guard',
            'email' => 'sec05_mgr@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'B01-MGR-S05',
            'pin_code' => Hash::make('9876'),
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);
        $this->manager->assignRole(StaffRole::BRANCH_MANAGER->value);

        $this->cashier = User::create([
            'name' => 'Cashier Pin Guard',
            'email' => 'sec05_csh@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'B01-CSH-S05',
            'pin_code' => Hash::make('1234'),
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);
        $this->cashier->assignRole(StaffRole::CASHIER->value);

        $this->meal = Meal::create([
            'name' => 'Lumpiang Shanghai',
            'menu_code' => 'B01-055',
            'category_id' => $category->id,
            'branch_id' => $this->branch->id,
            'price' => 180.00,
            'is_available' => true,
        ]);

        $createAction = new CreateOrderAction();
        $this->order = $createAction->execute([
            'branch_id' => $this->branch->id,
            'cashier_id' => $this->cashier->id,
            'order_type' => OrderType::DINE_IN->value,
            'items' => [
                ['meal_id' => $this->meal->id, 'quantity' => 1],
            ],
        ]);
    }

    public function test_manager_pin_verification_throttles_after_five_failed_attempts(): void
    {
        $this->actingAs($this->cashier);

        $component = new ManagerPinModal();
        $component->orderId = $this->order->id;

        $throttleKey = 'void-pin-approval:' . request()->ip() . ':' . $this->manager->id . ':' . $this->order->id;
        RateLimiter::clear($throttleKey);

        // 5 consecutive failed PIN attempts
        for ($i = 1; $i <= 5; $i++) {
            $success = $component->submitVoidRequest([
                'manager_id' => $this->manager->id,
                'manager_pin' => '0000', // Invalid PIN
                'reason' => 'Wrong entry',
            ]);
            $this->assertFalse($success);
            $this->assertEquals('Invalid manager PIN.', $component->errorMessage);
        }

        // 6th attempt should trigger rate limiting lockout
        $blockedSuccess = $component->submitVoidRequest([
            'manager_id' => $this->manager->id,
            'manager_pin' => '9876', // Correct PIN, but should be locked out!
            'reason' => 'Retry after lockout',
        ]);

        $this->assertFalse($blockedSuccess);
        $this->assertStringContainsString('Too many failed approval attempts', $component->errorMessage);
        $this->assertStringContainsString('Terminal locked for void approvals', $component->errorMessage);

        RateLimiter::clear($throttleKey);
    }

    public function test_audit_logs_record_approval_events_without_leaking_raw_pin(): void
    {
        Log::spy();
        $this->actingAs($this->cashier);

        $component = new ManagerPinModal();
        $component->orderId = $this->order->id;

        $throttleKey = 'void-pin-approval:' . request()->ip() . ':' . $this->manager->id . ':' . $this->order->id;
        RateLimiter::clear($throttleKey);

        $submittedPin = '9876';

        $success = $component->submitVoidRequest([
            'manager_id' => $this->manager->id,
            'manager_pin' => $submittedPin,
            'reason' => 'Customer left before food was served',
        ]);

        $this->assertTrue($success);

        // Verify Log::info was called with MANAGER_PIN_VOID_APPROVED
        Log::shouldHaveReceived('info')->withArgs(function ($message, $context) use ($submittedPin) {
            if ($message !== 'MANAGER_PIN_VOID_APPROVED') {
                return false;
            }

            // Strictly assert plain-text PIN is NOT in context
            if (isset($context['manager_pin'])) {
                return false;
            }
            if (in_array($submittedPin, $context, true)) {
                return false;
            }

            // Assert audit metadata is recorded
            return isset($context['order_id']) && isset($context['manager_id']) && isset($context['reason']);
        });

        RateLimiter::clear($throttleKey);
    }

    public function test_floor_pin_session_cannot_bypass_manager_pin_verification(): void
    {
        $this->actingAs($this->cashier);

        // Simulate floor terminal session authenticated via PIN
        session(['auth_method' => 'pin']);
        session([SystemConstants::SESSION_AUTH_METHOD => 'pin']);

        $action = new VoidOrderAction();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Floor terminal sessions cannot bypass manager PIN verification');

        $action->execute([
            'order_id' => $this->order->id,
            'manager_id' => $this->manager->id,
            'reason' => 'Attempted untrusted floor bypass',
            'skip_pin_check' => true, // Untrusted bypass attempt
        ]);
    }
}
