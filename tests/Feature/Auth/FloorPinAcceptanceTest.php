<?php

namespace Tests\Feature\Auth;

use App\Domains\Identity\Livewire\Auth\Login;
use App\Domains\Identity\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FloorPinAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->travelTo(now()->startOfSecond());
    }

    public static function invalidInputs(): array
    {
        return [
            'empty employee' => ['', '1234', 'emp_id'],
            'empty PIN' => ['B01-CSH-001', '', 'pin'],
            'short PIN' => ['B01-CSH-001', '123', 'pin'],
            'long PIN' => ['B01-CSH-001', '12345', 'pin'],
            'letters' => ['B01-CSH-001', 'abcd', 'pin'],
            'mixed PIN' => ['B01-CSH-001', '12a4', 'pin'],
            'spaces' => ['B01-CSH-001', ' 123', 'pin'],
        ];
    }

    #[DataProvider('invalidInputs')]
    public function test_invalid_inputs_are_rejected_without_authentication(string $employee, string $pin, string $field): void
    {
        Livewire::test(Login::class)->set('emp_id', $employee)->set('pin', $pin)
            ->call('login')->assertHasErrors([$field]);
        $this->assertGuest();
        $this->assertNull(session('auth_method'));
    }

    public static function invalidCredentials(): array
    {
        return ['unknown employee' => ['UNKNOWN-ID', '1234'], 'wrong PIN' => ['B01-CSH-001', '0000']];
    }

    #[DataProvider('invalidCredentials')]
    public function test_invalid_credentials_clear_pin_keep_employee_and_show_generic_error(string $employee, string $pin): void
    {
        Livewire::test(Login::class)->set('emp_id', $employee)->set('pin', $pin)->call('login')
            ->assertHasErrors(['emp_id'])->assertSee('Invalid Employee ID or PIN.')
            ->assertSet('pin', '')->assertSet('emp_id', $employee);
        $this->assertGuest();
        $this->assertSame(1, RateLimiter::attempts('floor-login-emp:' . strtolower($employee)));
    }

    public static function stations(): array
    {
        return [
            'cashier' => ['B01-CSH-001', '1234', 'pos'],
            'chef' => ['B01-CHF-001', '2345', 'menu'],
            'cook' => ['B01-COK-001', '3456', 'kitchen'],
            'manager' => ['B01-MGR-001', '9999', 'pos'],
        ];
    }

    #[DataProvider('stations')]
    public function test_success_rotates_session_and_redirects_to_configured_station(string $employee, string $pin, string $route): void
    {
        session()->start();
        $oldSession = session()->getId();
        session(['url.intended' => '/admin', 'terminal_locked' => true]);
        Livewire::test(Login::class)->set('emp_id', $employee)->set('pin', $pin)->call('login')
            ->assertHasNoErrors()->assertRedirect(route($route));
        $this->assertAuthenticatedAs(User::where('emp_id', $employee)->firstOrFail());
        $this->assertSame('pin', session('auth_method'));
        $this->assertNotSame($oldSession, session()->getId());
        $this->assertNull(session('url.intended'));
        $this->assertNull(session('terminal_locked'));
        $this->assertSame(auth()->user()->branch_id, session('current_branch_id'));
        $this->assertSame(0, RateLimiter::attempts('floor-login-emp:' . strtolower($employee)));
    }

    public function test_deactivated_employee_is_denied_without_session(): void
    {
        User::where('emp_id', 'B01-CSH-001')->update(['is_active' => false]);
        Livewire::test(Login::class)->set('emp_id', 'B01-CSH-001')->set('pin', '1234')->call('login')
            ->assertHasErrors(['emp_id'])->assertSet('pin', '')
            ->assertSee('Your account has been deactivated. Please contact your manager or system administrator.');
        $this->assertGuest();
    }

    public function test_inactive_branch_uses_requested_acceptance_message(): void
    {
        User::where('emp_id', 'B01-CSH-001')->firstOrFail()->branch->update(['is_active' => false]);
        Livewire::test(Login::class)->set('emp_id', 'B01-CSH-001')->set('pin', '1234')->call('login')
            ->assertHasErrors(['emp_id'])->assertSet('pin', '')
            ->assertSee('Branch is currently inactive');
        $this->assertGuest();
    }

    public function test_fifth_failure_locks_out_correct_pin_until_sixty_seconds_expire(): void
    {
        $component = Livewire::test(Login::class)->set('emp_id', 'B01-CSH-001');
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $component->set('pin', '0000')->call('login')->assertSee('Invalid Employee ID or PIN.');
            $this->assertSame($attempt, RateLimiter::attempts('floor-login-emp:b01-csh-001'));
        }
        $this->assertSame(60, RateLimiter::availableIn('floor-login-emp:b01-csh-001'));
        $component->set('pin', '1234')->call('login')->assertHasErrors(['emp_id'])
            ->assertSee(__('auth.throttle', ['seconds' => 60, 'minutes' => 1]));
        $this->assertGuest();
        $this->travel(61)->seconds();
        $component->set('pin', '1234')->call('login')->assertHasNoErrors()->assertRedirect(route('pos'));
        $this->assertAuthenticated();
    }

    public static function protectedStations(): array
    {
        return [['/pos'], ['/menu'], ['/kitchen']];
    }

    #[DataProvider('protectedStations')]
    public function test_guests_cannot_access_stations(string $url): void
    {
        $this->get($url)->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public static function adminUrls(): array
    {
        return [['/admin'], ['/admin/users'], ['/admin/sales-records'], ['/admin/sales-records/export-csv']];
    }

    #[DataProvider('adminUrls')]
    public function test_manager_pin_session_cannot_access_back_office_even_with_report_permissions(string $url): void
    {
        Livewire::test(Login::class)->set('emp_id', 'B01-MGR-001')->set('pin', '9999')->call('login');
        $this->get($url)->assertForbidden();
        $this->assertSame('pin', session('auth_method'));
    }

    public function test_cashier_cannot_access_other_stations_by_direct_url(): void
    {
        Livewire::test(Login::class)->set('emp_id', 'B01-CSH-001')->set('pin', '1234')->call('login');
        $this->get('/pos')->assertOk();
        $this->get('/menu')->assertForbidden();
        $this->get('/kitchen')->assertForbidden();
    }

    public function test_component_keypad_clear_backspace_and_length_limit(): void
    {
        $component = Livewire::test(Login::class);
        foreach (range(0, 9) as $digit) {
            $component->call('clearPin')->call('appendDigit', (string) $digit)->assertSet('pin', (string) $digit);
        }
        $component->set('pin', '1234')->call('appendDigit', '5')->assertSet('pin', '1234')
            ->call('backspace')->assertSet('pin', '123')->call('clearPin')->assertSet('pin', '');
    }

    public function test_success_before_lockout_resets_both_failed_attempt_counters(): void
    {
        $component = Livewire::test(Login::class)->set('emp_id', 'B01-CSH-001');
        for ($attempt = 0; $attempt < 4; $attempt++) {
            $component->set('pin', '0000')->call('login')->assertHasErrors(['emp_id']);
        }
        $component->set('pin', '1234')->call('login')->assertHasNoErrors();
        $this->assertSame(0, RateLimiter::attempts('floor-login-emp:b01-csh-001'));
        $this->assertSame(0, RateLimiter::attempts('b01-csh-001|127.0.0.1'));
    }

    public function test_employee_lockout_survives_ip_rotation(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->pinLoginRequest('0000', '192.0.2.' . $attempt)->assertOk();
            $this->assertSame(1, RateLimiter::attempts('b01-csh-001|192.0.2.' . $attempt));
        }
        $response = $this->pinLoginRequest('1234', '192.0.2.100')->assertOk();
        $snapshot = json_decode($response->json('components.0.snapshot'), true);
        $this->assertSame(__('auth.throttle', ['seconds' => 60, 'minutes' => 1]), $snapshot['memo']['errors']['emp_id'][0]);
        $this->assertGuest();
    }

    private function pinLoginRequest(string $pin, string $ip): \Illuminate\Testing\TestResponse
    {
        $this->withServerVariables(['REMOTE_ADDR' => $ip]);
        $html = $this->get('/login')->assertOk()->getContent();
        $this->assertSame(1, preg_match('/wire:snapshot="([^"]+)"/', $html, $matches));

        return $this->postJson('/livewire/update', ['components' => [[
            'snapshot' => html_entity_decode($matches[1], ENT_QUOTES),
            'updates' => ['emp_id' => 'B01-CSH-001', 'pin' => $pin],
            'calls' => [['path' => '', 'method' => 'login', 'params' => []]],
        ]]], ['X-Livewire' => 'true']);
    }

    public function test_successful_authentication_does_not_return_plaintext_pin_in_component_state(): void
    {
        Livewire::test(Login::class)->set('emp_id', 'B01-CSH-001')->set('pin', '1234')->call('login')
            ->assertHasNoErrors()->assertSet('pin', '');
    }

    public function test_logout_invalidates_pin_session_before_admin_login(): void
    {
        Livewire::test(Login::class)->set('emp_id', 'B01-MGR-001')->set('pin', '9999')->call('login');
        $this->post('/logout')->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertNull(session('auth_method'));
        $this->assertNull(session('current_branch_id'));
        $this->get('/admin/login')->assertOk();
    }
}
