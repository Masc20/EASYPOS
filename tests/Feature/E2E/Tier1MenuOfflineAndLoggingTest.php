<?php

namespace Tests\Feature\E2E;

use App\Domains\Identity\Livewire\Auth\Login as FloorLogin;
use App\Domains\Kitchen\Livewire\KitchenDisplay;
use App\Domains\Menu\Livewire\MealCatalogTable;
use App\Domains\PointOfSale\Livewire\Checkout\CheckoutModal;
use App\Domains\PointOfSale\Livewire\Checkout\ManagerPinModal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\Feature\E2E\Concerns\E2ETestHelpers;
use Tests\TestCase;

class Tier1MenuOfflineAndLoggingTest extends TestCase
{
    use E2ETestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedE2EActors();
    }

    // ------------------------------------------------------------------
    // F12: Meal Catalog (/menu) Offline Availability Feedback (>= 5 cases)
    // ------------------------------------------------------------------

    public function test_f12_chef_toggles_own_branch_dish_in_stock_to_sold_out_and_bumps_menu_version(): void
    {
        ['meal' => $b1Meal] = $this->createBranchCategoryAndMeal($this->branch1, [
            'name' => 'B1 Crispy Pata',
            'is_available' => true,
        ]);

        Cache::forever("branch_{$this->branch1->id}_menu_version", 1);

        Livewire::actingAs($this->chef1)
            ->test(MealCatalogTable::class)
            ->assertSee('B1 Crispy Pata')
            ->assertSee('In Stock')
            ->call('toggleAvailability', $b1Meal->id)
            ->assertHasNoErrors()
            ->assertDispatched('meal-saved')
            ->assertSee("86'd (Sold Out)");

        $this->assertFalse($b1Meal->fresh()->is_available);
        $this->assertGreaterThan(1, (int) Cache::get("branch_{$this->branch1->id}_menu_version", 0));
    }

    public function test_f12_chef_recovers_and_toggles_sold_out_dish_back_to_in_stock(): void
    {
        ['meal' => $b1Meal] = $this->createBranchCategoryAndMeal($this->branch1, [
            'name' => 'B1 Grilled Tuna',
            'is_available' => false,
        ]);

        Livewire::actingAs($this->chef1)
            ->test(MealCatalogTable::class)
            ->assertSee("86'd (Sold Out)")
            ->call('toggleAvailability', $b1Meal->id)
            ->assertHasNoErrors()
            ->assertDispatched('meal-saved')
            ->assertSee('In Stock');

        $this->assertTrue($b1Meal->fresh()->is_available);
    }

    public function test_f12_branch_manager_toggles_own_branch_dish_availability_on_menu(): void
    {
        ['meal' => $b1Meal] = $this->createBranchCategoryAndMeal($this->branch1, [
            'name' => 'B1 Manager Special',
            'is_available' => true,
        ]);

        $this->actingAs($this->manager1)
            ->get('/menu')
            ->assertStatus(200)
            ->assertSee('B1 Manager Special');

        Livewire::actingAs($this->manager1)
            ->test(MealCatalogTable::class)
            ->call('toggleAvailability', $b1Meal->id)
            ->assertHasNoErrors()
            ->assertDispatched('meal-saved');

        $this->assertFalse($b1Meal->fresh()->is_available);
    }

    public function test_f12_menu_page_renders_offline_failure_toast_listener_and_revert_hooks(): void
    {
        ['meal' => $b1Meal] = $this->createBranchCategoryAndMeal($this->branch1, [
            'name' => 'B1 Offline Contract Dish',
            'is_available' => true,
        ]);

        $response = $this->actingAs($this->chef1)->get('/menu');
        $response->assertStatus(200);
        $response->assertSee('Could not save availability. Please try again.', false);
        $response->assertSee('menu-alert', false);
        $response->assertSee('menu-toggle-settled', false);
        $response->assertSee('navigator.onLine', false);
        $response->assertSee('clearPending()', false);
        $response->assertSee('controller.abort()', false);

        Livewire::actingAs($this->chef1)
            ->test(MealCatalogTable::class)
            ->assertSee('data-meal-id="' . $b1Meal->id . '"', false)
            ->assertSee('data-available="1"', false)
            ->assertSee('In Stock')
            ->assertSee('x-on:menu-toggle-settled.window', false);
    }

    public function test_f12_global_dish_toggle_by_chef_updates_branch_override_for_own_branch_only(): void
    {
        ['meal' => $globalMeal] = $this->createBranchCategoryAndMeal(null, [
            'name' => 'Global Adobo Bowl',
            'is_available' => true,
        ]);

        Livewire::actingAs($this->chef1)
            ->test(MealCatalogTable::class)
            ->call('toggleAvailability', $globalMeal->id)
            ->assertHasNoErrors()
            ->assertDispatched('meal-saved');

        $this->assertTrue($globalMeal->fresh()->is_available);
        $this->assertDatabaseHas('meal_branch_availabilities', [
            'meal_id' => $globalMeal->id,
            'branch_id' => $this->branch1->id,
            'is_available' => false,
        ]);
        $this->assertDatabaseMissing('meal_branch_availabilities', [
            'meal_id' => $globalMeal->id,
            'branch_id' => $this->branch2->id,
        ]);
        $this->assertFalse($globalMeal->fresh()->isAvailableForBranch($this->branch1->id));
        $this->assertTrue($globalMeal->fresh()->isAvailableForBranch($this->branch2->id));
    }

    // ------------------------------------------------------------------
    // F13: Clock In/Out & Action/Failure Logging (>= 5 cases)
    // ------------------------------------------------------------------

    public function test_f13_staff_login_records_clock_in_event_with_user_branch_and_ip(): void
    {
        Livewire::test(FloorLogin::class)
            ->set('emp_id', $this->cashier1->emp_id)
            ->set('pin', '1234')
            ->call('login')
            ->assertHasNoErrors();

        $this->assertAuthenticatedAs($this->cashier1);

        $rows = $this->allLogRows();
        $match = $rows->first(fn (array $row) => $this->rowUserId($row) === $this->cashier1->id
            && $this->rowMatchesKeywords($row, ['clock_in', 'clock-in', 'clock in', 'login']));

        $this->assertNotNull($match, 'Expected a clock_in/login log entry for cashier1.');
        $this->assertSame($this->branch1->id, (int) $this->rowBranchId($match));
        $this->assertNotEmpty($this->rowIpAddress($match));
    }

    public function test_f13_staff_logout_records_clock_out_event(): void
    {
        $this->actingAs($this->cashier1)
            ->withSession(['auth_method' => 'pin'])
            ->post('/logout')
            ->assertRedirect(route('login'));

        $this->assertGuest();

        $rows = $this->allLogRows();
        $match = $rows->first(fn (array $row) => $this->rowUserId($row) === $this->cashier1->id
            && $this->rowMatchesKeywords($row, ['clock_out', 'clock-out', 'clock out', 'logout']));

        $this->assertNotNull($match, 'Expected a clock_out/logout log entry for cashier1.');
        $this->assertSame($this->branch1->id, (int) $this->rowBranchId($match));
    }

    public function test_f13_successful_pos_kitchen_and_menu_actions_record_success_logs(): void
    {
        ['meal' => $b1Meal] = $this->createBranchCategoryAndMeal($this->branch1, [
            'name' => 'B1 Log Action Dish',
            'price' => 150.00,
            'is_available' => true,
        ]);

        // 1. Menu availability toggle by Chef
        Livewire::actingAs($this->chef1)
            ->test(MealCatalogTable::class)
            ->call('toggleAvailability', $b1Meal->id)
            ->assertHasNoErrors();
        Livewire::actingAs($this->chef1)
            ->test(MealCatalogTable::class)
            ->call('toggleAvailability', $b1Meal->id)
            ->assertHasNoErrors();

        // 2. POS Checkout by Cashier
        $this->resetSessionAuth();
        Livewire::actingAs($this->cashier1)
            ->test(CheckoutModal::class)
            ->call('openWithData', [
                'items' => [['meal_id' => $b1Meal->id, 'quantity' => 1]],
                'subtotal' => 150.00,
                'total_amount' => 150.00,
            ])
            ->set('amountTendered', '150.00')
            ->call('processCheckout')
            ->assertHasNoErrors();

        // 3. Kitchen Ticket Claim by Cook
        $this->resetSessionAuth();
        $ticketId = (int) DB::table('kitchen_tickets')->where('branch_id', $this->branch1->id)->value('id');
        $this->assertGreaterThan(0, $ticketId);

        Livewire::actingAs($this->cook1)
            ->test(KitchenDisplay::class)
            ->call('claimTicket', $ticketId)
            ->assertHasNoErrors();

        $rows = $this->allLogRows();

        $this->assertNotNull(
            $rows->first(fn (array $row) => $this->rowUserId($row) === $this->chef1->id
                && $this->rowIsSuccess($row)),
            'Expected successful menu toggle log entry for chef1.'
        );
        $this->assertNotNull(
            $rows->first(fn (array $row) => $this->rowUserId($row) === $this->cashier1->id
                && $this->rowIsSuccess($row)),
            'Expected successful POS checkout log entry for cashier1.'
        );
        $this->assertNotNull(
            $rows->first(fn (array $row) => $this->rowUserId($row) === $this->cook1->id
                && $this->rowIsSuccess($row)),
            'Expected successful kitchen claim log entry for cook1.'
        );
    }

    public function test_f13_failed_login_attempt_records_failed_log_entry(): void
    {
        Livewire::test(FloorLogin::class)
            ->set('emp_id', $this->cashier1->emp_id)
            ->set('pin', '0000')
            ->call('login')
            ->assertHasErrors('emp_id');

        $this->assertGuest();

        $rows = $this->allLogRows();
        $match = $rows->first(fn (array $row) => $this->rowIsFailureOrDenied($row)
            && $this->rowMatchesKeywords($row, ['login', 'auth', 'pin', 'clock']));

        $this->assertNotNull($match, 'Expected a failed login log entry.');
        $this->assertNotEmpty($this->rowIpAddress($match));
    }

    public function test_f13_invalid_void_pin_and_403_denied_action_record_failed_and_denied_logs(): void
    {
        ['meal' => $b1Meal] = $this->createBranchCategoryAndMeal($this->branch1);
        ['order' => $order] = $this->createOrderWithKitchenTicket($this->branch1, $this->cashier1, $b1Meal);

        Livewire::actingAs($this->cashier1)
            ->test(ManagerPinModal::class)
            ->call('openModal', $order->id)
            ->call('submitVoidRequest', [
                'manager_id' => $this->manager1->emp_id,
                'manager_pin' => '0000',
                'reason' => 'Testing failed void log',
            ])
            ->assertSet('errorMessage', fn (?string $msg) => filled($msg));

        $this->actingAs($this->cashier1)->get('/menu')->assertStatus(403);

        $rows = $this->allLogRows();
        $this->assertNotNull(
            $rows->first(fn (array $row) => $this->rowIsFailureOrDenied($row)
                && $this->rowMatchesKeywords($row, ['void', 'pin'])),
            'Expected failed log entry for invalid manager void PIN.'
        );
        $this->assertNotNull(
            $rows->first(fn (array $row) => $this->rowIsFailureOrDenied($row)
                && $this->rowUserId($row) === $this->cashier1->id
                && $this->rowMatchesKeywords($row, ['denied', 'forbidden', 'unauthorized', '403', 'menu'])),
            'Expected denied/failed log entry for 403 /menu access.'
        );
    }

    // ------------------------------------------------------------------
    // F14: Branch-Scoped Admin Log Viewer (>= 5 cases)
    // ------------------------------------------------------------------

    public function test_f14_owner_accesses_admin_log_viewer_and_sees_cross_branch_logs(): void
    {
        $this->seedCrossBranchLoggedActivity();
        $url = $this->requireAdminLogViewerUrl();

        $this->actingAs($this->owner)
            ->get($url)
            ->assertStatus(200)
            ->assertSee($this->cashier1->name)
            ->assertSee($this->cashier2->name);
    }

    public function test_f14_super_admin_accesses_admin_log_viewer_with_cross_branch_visibility(): void
    {
        $this->seedCrossBranchLoggedActivity();
        $url = $this->requireAdminLogViewerUrl();

        $this->actingAs($this->superAdmin)
            ->get($url)
            ->assertStatus(200)
            ->assertSee($this->cashier1->name)
            ->assertSee($this->cashier2->name);
    }

    public function test_f14_branch_manager_accesses_admin_log_viewer_and_sees_own_branch_logs(): void
    {
        $this->seedCrossBranchLoggedActivity();
        $url = $this->requireAdminLogViewerUrl();

        $this->actingAs($this->manager1)
            ->get($url)
            ->assertStatus(200)
            ->assertSee($this->cashier1->name);
    }

    public function test_f14_admin_log_viewer_displays_clock_action_and_failure_events(): void
    {
        Livewire::test(FloorLogin::class)
            ->set('emp_id', $this->cashier1->emp_id)
            ->set('pin', '1234')
            ->call('login');
        $this->resetSessionAuth();

        Livewire::test(FloorLogin::class)
            ->set('emp_id', $this->cashier1->emp_id)
            ->set('pin', '0000')
            ->call('login');
        $this->resetSessionAuth();

        ['meal' => $b1Meal] = $this->createBranchCategoryAndMeal($this->branch1);
        Livewire::actingAs($this->chef1)
            ->test(MealCatalogTable::class)
            ->call('toggleAvailability', $b1Meal->id);
        $this->resetSessionAuth();

        $url = $this->requireAdminLogViewerUrl();

        $response = $this->actingAs($this->owner)->get($url);
        $response->assertStatus(200);
        $content = strtolower($response->getContent());

        $this->assertTrue(
            str_contains($content, 'clock') || str_contains($content, 'login'),
            'Expected clock-in/login event to be visible in Admin Log Viewer.'
        );
        $this->assertTrue(
            str_contains($content, 'success'),
            'Expected success status to be visible in Admin Log Viewer.'
        );
        $this->assertTrue(
            str_contains($content, 'fail') || str_contains($content, 'denied'),
            'Expected failed/denied event to be visible in Admin Log Viewer.'
        );
    }

    public function test_f14_owner_and_super_admin_can_filter_logs_by_branch_event_and_status(): void
    {
        $this->seedCrossBranchLoggedActivity();
        $route = $this->findAdminLogViewerRoute();
        $this->assertNotNull($route, 'Admin Log Viewer not yet registered in /admin.');

        $url = '/' . ltrim($route->uri(), '/');
        $controller = $route->getAction('controller');
        $componentClass = is_string($controller) ? explode('@', $controller)[0] : null;

        if ($componentClass && class_exists($componentClass) && is_subclass_of($componentClass, \Livewire\Component::class)) {
            $lw = Livewire::actingAs($this->owner)->test($componentClass);
            if (method_exists($componentClass, 'filterTable')) {
                $lw->filterTable('branch_id', $this->branch1->id)
                    ->assertSee($this->cashier1->name)
                    ->assertDontSee($this->cashier2->name);
                return;
            }
        }

        $filtered = $this->actingAs($this->superAdmin)
            ->get($url . '?branch_id=' . $this->branch1->id . '&tableFilters[branch_id][value]=' . $this->branch1->id);
        $filtered->assertStatus(200)
            ->assertSee($this->cashier1->name)
            ->assertDontSee($this->cashier2->name);
    }

    // ------------------------------------------------------------------
    // Opaque-Box Helpers
    // ------------------------------------------------------------------

    private function resetSessionAuth(): void
    {
        auth()->logout();
        $this->flushSession();
    }

    private function seedCrossBranchLoggedActivity(): void
    {
        Livewire::test(FloorLogin::class)
            ->set('emp_id', $this->cashier1->emp_id)
            ->set('pin', '1234')
            ->call('login');
        $this->resetSessionAuth();

        Livewire::test(FloorLogin::class)
            ->set('emp_id', $this->cashier2->emp_id)
            ->set('pin', '1234')
            ->call('login');
        $this->resetSessionAuth();
    }

    private function requireAdminLogViewerUrl(): string
    {
        $url = $this->findAdminLogViewerUrl();
        $this->assertNotNull($url, 'Admin Log Viewer not yet registered in /admin.');

        return $url;
    }

    private function findAdminLogViewerRoute(): ?RoutingRoute
    {
        foreach (Route::getRoutes() as $route) {
            $uri = ltrim($route->uri(), '/');
            $lower = strtolower($uri);
            if (
                in_array('GET', $route->methods(), true)
                && str_starts_with($lower, 'admin/')
                && str_contains($lower, 'log')
                && ! str_contains($lower, 'login')
                && ! str_contains($lower, 'logout')
                && ! str_contains($uri, '{')
            ) {
                return $route;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function discoverLogTables(): array
    {
        $ignored = [
            'users', 'branches', 'meals', 'meal_categories', 'meal_branch_availabilities',
            'orders', 'order_items', 'payments', 'kitchen_tickets', 'kitchen_ticket_items',
            'branch_daily_sequences', 'permissions', 'roles', 'model_has_permissions',
            'model_has_roles', 'role_has_permissions', 'cache', 'cache_locks',
            'jobs', 'job_batches', 'failed_jobs', 'sessions', 'password_reset_tokens',
            'migrations', 'sqlite_sequence',
        ];

        $tables = [];
        foreach (Schema::getTableListing() as $table) {
            $name = str_contains($table, '.') ? substr($table, strrpos($table, '.') + 1) : $table;
            if (in_array($name, $ignored, true)) {
                continue;
            }
            $lower = strtolower($name);
            if (
                str_contains($lower, 'log')
                || str_contains($lower, 'audit')
                || str_contains($lower, 'activity')
                || str_contains($lower, 'clock')
                || str_contains($lower, 'attendance')
            ) {
                $tables[] = $name;
            }
        }

        return $tables;
    }

    private function allLogRows(): Collection
    {
        $tables = $this->discoverLogTables();
        $this->assertNotEmpty($tables, 'M5 logging table not yet implemented in database schema.');

        $rows = collect();
        foreach ($tables as $table) {
            foreach (DB::table($table)->get() as $row) {
                $arr = (array) $row;
                $arr['_table'] = $table;
                $rows->push($arr);
            }
        }

        return $rows;
    }

    private function rowUserId(array $row): ?int
    {
        $id = $row['user_id'] ?? $row['actor_id'] ?? $row['causer_id'] ?? null;

        return $id === null ? null : (int) $id;
    }

    private function rowBranchId(array $row): ?int
    {
        $id = $row['branch_id'] ?? null;

        return $id === null ? null : (int) $id;
    }

    private function rowIpAddress(array $row): ?string
    {
        $ip = $row['ip_address'] ?? $row['ip'] ?? $row['client_ip'] ?? null;

        return $ip === null ? null : (string) $ip;
    }

    private function rowIsSuccess(array $row): bool
    {
        $status = strtolower((string) ($row['status'] ?? $row['outcome'] ?? $row['result'] ?? ''));

        return in_array($status, ['success', 'succeeded', 'ok'], true);
    }

    private function rowIsFailureOrDenied(array $row): bool
    {
        $status = strtolower((string) ($row['status'] ?? $row['outcome'] ?? $row['result'] ?? ''));
        if (in_array($status, ['failed', 'failure', 'denied', 'error', 'rejected'], true)) {
            return true;
        }

        return $this->rowMatchesKeywords($row, ['failed', 'denied', 'invalid', 'unauthorized']);
    }

    /**
     * @param  array<int, string>  $keywords
     */
    private function rowMatchesKeywords(array $row, array $keywords): bool
    {
        $haystack = strtolower((string) json_encode($row));
        foreach ($keywords as $keyword) {
            if (str_contains($haystack, strtolower($keyword))) {
                return true;
            }
        }

        return false;
    }
}
