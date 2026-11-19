<?php

namespace Tests\Feature\Security;

use App\Domains\Branches\Models\Branch;
use App\Domains\Identity\Events\UserLoggedOut;
use App\Domains\Identity\Livewire\Auth\Login;
use App\Domains\Identity\Models\User;
use App\Support\Constants\SystemConstants;
use App\Support\Enums\StaffRole;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Tests\TestCase;

class StrictLogoutCsrfMiddleware extends ValidateCsrfToken
{
    protected function runningUnitTests()
    {
        return false;
    }
}

class PageLockAndLogoutTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branch1;
    protected Branch $branch2;
    protected User $cashier;
    protected User $otherCashier;
    protected User $b1Manager;
    protected User $b2Manager;
    protected User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->branch1 = Branch::where('code', 'B01')->firstOrFail();
        $this->branch2 = Branch::firstOrCreate(['code' => 'B02'], [
            'name' => 'Second Branch',
            'is_active' => true,
        ]);

        $this->cashier = User::where('emp_id', 'B01-CSH-001')->firstOrFail();
        $this->b1Manager = User::where('emp_id', 'B01-MGR-001')->firstOrFail();
        $this->owner = User::where('email', 'owner@easypos.com')->firstOrFail();

        $this->otherCashier = User::create([
            'name' => 'Other Cashier',
            'email' => null,
            'password' => null,
            'emp_id' => 'B01-CSH-099',
            'branch_id' => $this->branch1->id,
            'pin_code' => Hash::make('4321'),
            'is_active' => true,
        ]);
        $this->otherCashier->assignRole(StaffRole::CASHIER->value);

        $this->b2Manager = User::create([
            'name' => 'Branch 2 Manager',
            'email' => 'mgr-b02-lock@easypos.test',
            'password' => Hash::make('Str0ng!Pass#2026'),
            'emp_id' => 'B02-MGR-901',
            'branch_id' => $this->branch2->id,
            'pin_code' => Hash::make('8888'),
            'is_active' => true,
        ]);
        $this->b2Manager->assignRole(StaffRole::BRANCH_MANAGER->value);
    }

    public function test_guests_cannot_access_terminal_lock_routes(): void
    {
        $this->get('/terminal/lock')->assertRedirect(route('login'));
        $this->post('/terminal/lock')->assertRedirect(route('login'));
        $this->post('/terminal/unlock', ['pin' => '1234'])->assertRedirect(route('login'));
    }

    public function test_locking_terminal_persists_across_reloads_direct_routes_admin_and_livewire_calls(): void
    {
        $lockResponse = $this->actingAs($this->cashier)
            ->withSession([SystemConstants::SESSION_AUTH_METHOD => 'pin'])
            ->post('/terminal/lock', ['return_url' => '/pos']);

        $lockResponse->assertRedirect(route('terminal.lock.show'));
        $this->assertTrue((bool) session(SystemConstants::SESSION_TERMINAL_LOCKED));
        $this->assertSame($this->cashier->id, session(SystemConstants::SESSION_TERMINAL_LOCKED_BY));

        $lockPage = $this->actingAs($this->cashier)->get('/terminal/lock');
        $lockPage->assertStatus(200);
        $this->assertStringContainsString('no-store', (string) $lockPage->headers->get('Cache-Control'));
        $lockPage->assertHeader('Pragma', 'no-cache');
        $lockPage->assertDontSee('wire:poll', false);

        // Reloading /pos or navigating to any floor route while locked redirects back to /terminal/lock
        $this->actingAs($this->cashier)->get('/pos')->assertRedirect(route('terminal.lock.show'));
        $this->actingAs($this->cashier)->get('/')->assertRedirect(route('terminal.lock.show'));
        $this->actingAs($this->cashier)->get('/menu')->assertRedirect(route('terminal.lock.show'));
        $this->actingAs($this->cashier)->get('/kitchen')->assertRedirect(route('terminal.lock.show'));
        $this->actingAs($this->cashier)->get('/admin')->assertStatus(403);

        // Livewire updates while locked redirect cleanly; JSON APIs receive 423 Locked
        $this->actingAs($this->cashier)
            ->withHeaders(['X-Livewire' => 'true'])
            ->post('/livewire/update', [])
            ->assertRedirect(route('terminal.lock.show'));

        $this->flushHeaders();

        $this->actingAs($this->cashier)
            ->getJson('/pos')
            ->assertStatus(423)
            ->assertJson(['locked' => true]);
    }

    public function test_pos_non_empty_cart_is_temporarily_saved_on_terminal_lock_and_restored_after_unlock(): void
    {
        $cartPayload = json_encode([
            'items' => [
                [
                    'meal_id' => 101,
                    'name' => 'Crispy Pata Platter',
                    'menu_code' => 'M-101',
                    'price' => 650.00,
                    'quantity' => 2,
                    'special_instructions' => 'Extra garlic sauce',
                    'max_quantity' => null,
                ],
            ],
            'orderType' => 'dine_in',
            'tableNumber' => 'T-08',
            'customerName' => 'Juan Dela Cruz',
            'discountType' => 'senior_pwd',
            'customDiscount' => 0,
            'notes' => 'VIP table',
        ]);

        $this->actingAs($this->cashier)
            ->withSession([SystemConstants::SESSION_AUTH_METHOD => 'pin'])
            ->post('/terminal/lock', [
                'return_url' => '/pos',
                'cart_state' => $cartPayload,
            ])
            ->assertRedirect(route('terminal.lock.show'));

        $this->actingAs($this->cashier)
            ->post('/terminal/unlock', [
                'unlock_mode' => 'self',
                'pin' => '1234',
            ])
            ->assertRedirect('/pos');

        $savedCart = session(\App\Domains\PointOfSale\Livewire\Checkout\CheckoutCart::savedCartSessionKey($this->cashier->id));
        $this->assertIsArray($savedCart);
        $this->assertCount(1, $savedCart['items']);
        $this->assertSame('Crispy Pata Platter', $savedCart['items'][0]['name']);
        $this->assertSame(2, $savedCart['items'][0]['quantity']);
        $this->assertSame('T-08', $savedCart['tableNumber']);
        $this->assertSame('senior_pwd', $savedCart['discountType']);

        $cartComponent = \Livewire\Livewire::actingAs($this->cashier)
            ->test(\App\Domains\PointOfSale\Livewire\Checkout\CheckoutCart::class);

        $cartComponent
            ->assertSet('tableNumber', 'T-08')
            ->assertSet('customerName', 'Juan Dela Cruz')
            ->assertSet('discountType', 'senior_pwd');

        $this->assertCount(1, $cartComponent->get('items'));
        $this->assertSame(2, $cartComponent->get('items')[0]['quantity']);
    }

    public function test_invalid_unauthorized_cross_branch_and_throttled_unlock_attempts_keep_terminal_locked(): void
    {
        RateLimiter::clear('terminal-unlock:' . $this->cashier->id . '|127.0.0.1');

        $this->actingAs($this->cashier)
            ->withSession([
                SystemConstants::SESSION_AUTH_METHOD => 'pin',
                SystemConstants::SESSION_TERMINAL_LOCKED => true,
                SystemConstants::SESSION_TERMINAL_LOCK_RETURN_URL => '/pos',
            ]);

        // Wrong self PIN
        $this->post('/terminal/unlock', ['unlock_mode' => 'self', 'pin' => '9999'])
            ->assertSessionHasErrors('credential');
        $this->assertTrue((bool) session(SystemConstants::SESSION_TERMINAL_LOCKED));

        // Another floor cashier cannot override unlock
        $this->post('/terminal/unlock', [
            'unlock_mode' => 'override',
            'emp_id' => $this->otherCashier->emp_id,
            'pin' => '4321',
        ])->assertSessionHasErrors('credential');
        $this->assertTrue((bool) session(SystemConstants::SESSION_TERMINAL_LOCKED));

        // Cross-branch manager (B02) cannot override unlock B01 cashier
        $this->post('/terminal/unlock', [
            'unlock_mode' => 'override',
            'emp_id' => $this->b2Manager->emp_id,
            'pin' => '8888',
        ])->assertSessionHasErrors('credential');
        $this->assertTrue((bool) session(SystemConstants::SESSION_TERMINAL_LOCKED));

        // Deactivated manager cannot override unlock
        $this->b1Manager->update(['is_active' => false]);
        $this->post('/terminal/unlock', [
            'unlock_mode' => 'override',
            'emp_id' => $this->b1Manager->emp_id,
            'pin' => '9999',
        ])->assertSessionHasErrors('credential');
        $this->assertTrue((bool) session(SystemConstants::SESSION_TERMINAL_LOCKED));
        $this->b1Manager->update(['is_active' => true]);

        // 5th failure triggers lockout on 6th attempt
        $this->post('/terminal/unlock', ['unlock_mode' => 'self', 'pin' => '0000'])
            ->assertSessionHasErrors('credential');
        $response = $this->post('/terminal/unlock', ['unlock_mode' => 'self', 'pin' => '1234']);
        $response->assertSessionHasErrors('credential');
        $this->assertStringContainsString('Too many failed unlock attempts', session('errors')->first('credential'));
        $this->assertTrue((bool) session(SystemConstants::SESSION_TERMINAL_LOCKED));
    }

    public function test_valid_self_same_branch_manager_and_owner_unlocks_clear_lock_and_sanitize_return_url(): void
    {
        RateLimiter::clear('terminal-unlock:' . $this->cashier->id . '|127.0.0.1');

        // Self PIN unlock also neutralizes open-redirect return_url
        $this->actingAs($this->cashier)
            ->withSession([SystemConstants::SESSION_AUTH_METHOD => 'pin'])
            ->post('/terminal/lock', ['return_url' => 'https://evil.example/steal'])
            ->assertRedirect(route('terminal.lock.show'));

        $this->post('/terminal/unlock', ['unlock_mode' => 'self', 'pin' => '1234'])
            ->assertRedirect(route('pos'));
        $this->assertFalse((bool) session(SystemConstants::SESSION_TERMINAL_LOCKED));

        // Same-branch manager PIN override unlock
        $this->actingAs($this->cashier)
            ->withSession([SystemConstants::SESSION_AUTH_METHOD => 'pin'])
            ->post('/terminal/lock', ['return_url' => '/pos'])
            ->assertRedirect(route('terminal.lock.show'));

        $this->post('/terminal/unlock', [
            'unlock_mode' => 'override',
            'emp_id' => $this->b1Manager->emp_id,
            'pin' => '9999',
        ])->assertRedirect('/pos');
        $this->assertFalse((bool) session(SystemConstants::SESSION_TERMINAL_LOCKED));

        // Executive (Owner) email+password override unlock
        $this->actingAs($this->cashier)
            ->withSession([SystemConstants::SESSION_AUTH_METHOD => 'pin'])
            ->post('/terminal/lock', ['return_url' => '/pos'])
            ->assertRedirect(route('terminal.lock.show'));

        $this->post('/terminal/unlock', [
            'unlock_mode' => 'override',
            'email' => $this->owner->email,
            'password' => 'password',
        ])->assertRedirect('/pos');
        $this->assertFalse((bool) session(SystemConstants::SESSION_TERMINAL_LOCKED));
    }

    public function test_fresh_pin_login_clears_any_stale_terminal_lock_session_keys(): void
    {
        $this->withSession([
            SystemConstants::SESSION_TERMINAL_LOCKED => true,
            SystemConstants::SESSION_TERMINAL_LOCKED_AT => now()->toIso8601String(),
            SystemConstants::SESSION_TERMINAL_LOCKED_BY => $this->cashier->id,
            SystemConstants::SESSION_TERMINAL_LOCK_RETURN_URL => '/pos',
        ]);

        Livewire::test(Login::class)
            ->set('emp_id', 'B01-CSH-001')
            ->set('pin', '1234')
            ->call('login')
            ->assertHasNoErrors()
            ->assertRedirect(route('pos'));

        $this->assertFalse((bool) session(SystemConstants::SESSION_TERMINAL_LOCKED));
    }

    public function test_logout_while_locked_or_already_unauthenticated_redirects_cleanly_without_403_or_419(): void
    {
        Event::fake([UserLoggedOut::class]);

        // Locked floor cashier logs out cleanly
        $this->actingAs($this->cashier)
            ->withSession([
                SystemConstants::SESSION_AUTH_METHOD => 'pin',
                SystemConstants::SESSION_TERMINAL_LOCKED => true,
            ])
            ->post('/logout')
            ->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertFalse((bool) session(SystemConstants::SESSION_TERMINAL_LOCKED));
        Event::assertDispatched(UserLoggedOut::class);

        // Duplicate / already-unauthenticated slow-network logout POST does not return 401/403/419
        $this->post('/logout')->assertRedirect(route('login'));
        $this->withHeaders(['Referer' => 'http://localhost/admin'])
            ->post('/logout')
            ->assertRedirect('/admin/login');
    }

    public function test_expired_csrf_419_on_logout_and_admin_logout_redirects_cleanly_while_other_routes_keep_419(): void
    {
        Route::middleware(['web', StrictLogoutCsrfMiddleware::class])->post('/_test/strict-non-logout', fn () => response('OK'));

        // Re-wrap logout route with strict CSRF middleware to simulate expired CSRF token under slow network
        Route::middleware(['web', StrictLogoutCsrfMiddleware::class])->post('/logout', function () {
            return response('Should not hit route when CSRF fails', 500);
        });
        Route::middleware(['web', StrictLogoutCsrfMiddleware::class])->post('/admin/logout', function () {
            return response('Should not hit route when CSRF fails', 500);
        });

        // Floor cashier submitting stale CSRF on /logout -> 302 to /login (NEVER 419 or 403)
        $floorLogout = $this->actingAs($this->cashier)
            ->withSession([
                '_token' => 'valid_token',
                SystemConstants::SESSION_AUTH_METHOD => 'pin',
            ])
            ->post('/logout', ['_token' => 'stale_expired_token']);

        $floorLogout->assertRedirect(route('login'));
        $this->assertGuest();

        // Admin submitting stale CSRF on /admin/logout -> 302 to /admin/login (NEVER 419 or 403)
        $adminLogout = $this->actingAs($this->b1Manager)
            ->withSession(['_token' => 'valid_token'])
            ->post('/admin/logout', ['_token' => 'stale_expired_token']);

        $adminLogout->assertRedirect('/admin/login');
        $this->assertGuest();

        // Non-logout routes STILL enforce 419 on CSRF mismatch
        $this->withSession(['_token' => 'valid_token'])
            ->post('/_test/strict-non-logout', ['_token' => 'stale_expired_token'])
            ->assertStatus(419);
    }
}
