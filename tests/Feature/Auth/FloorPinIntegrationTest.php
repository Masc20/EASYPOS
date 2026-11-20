<?php

namespace Tests\Feature\Auth;

use App\Domains\Identity\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FloorPinIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->travelTo(now()->startOfSecond());
    }

    private function submitPin(string $employee, string $pin, string $ip = '127.0.0.1'): array
    {
        $this->withServerVariables(['REMOTE_ADDR' => $ip]);
        $html = $this->get('/login')->assertOk()->getContent();
        $this->assertSame(1, preg_match('/wire:snapshot="([^"]+)"/', $html, $matches));

        $response = $this->postJson('/livewire/update', ['components' => [[
            'snapshot' => html_entity_decode($matches[1], ENT_QUOTES),
            'updates' => ['emp_id' => $employee, 'pin' => $pin],
            'calls' => [['path' => '', 'method' => 'login', 'params' => []]],
        ]]], ['X-Livewire' => 'true'])->assertOk();

        return [
            json_decode($response->json('components.0.snapshot'), true, 512, JSON_THROW_ON_ERROR),
            $response->json('components.0.effects'),
        ];
    }

    public static function stationUsers(): array
    {
        return [
            'cashier' => ['B01-CSH-001', '1234', '/pos'],
            'chef' => ['B01-CHF-001', '2345', '/menu'],
            'cook' => ['B01-COK-001', '3456', '/kitchen'],
            'branch manager' => ['B01-MGR-001', '9999', '/pos'],
        ];
    }

    #[DataProvider('stationUsers')]
    public function test_http_pin_login_establishes_station_session(string $employee, string $pin, string $station): void
    {
        [$snapshot, $effects] = $this->submitPin($employee, $pin);

        $this->assertAuthenticatedAs(User::where('emp_id', $employee)->firstOrFail());
        $this->assertSame('pin', session('auth_method'));
        $this->assertSame(auth()->user()->branch_id, session('current_branch_id'));
        $this->assertSame('', $snapshot['data']['pin']);
        $this->assertSame(url($station), $effects['redirect']);
        $this->get($station)->assertOk();
        $this->get('/admin')->assertForbidden();
    }

    public function test_cashier_http_session_cannot_open_other_stations_or_admin_exports(): void
    {
        $this->submitPin('B01-CSH-001', '1234');

        $this->get('/pos')->assertOk();
        $this->get('/menu')->assertForbidden();
        $this->get('/kitchen')->assertForbidden();
        $this->get('/admin/sales-records/export-csv')->assertForbidden();
    }

    public function test_wrong_pin_rejects_login_and_preserves_employee_for_retry(): void
    {
        [$snapshot] = $this->submitPin('B01-CSH-001', '0000');

        $this->assertGuest();
        $this->assertSame('Invalid Employee ID or PIN.', $snapshot['memo']['errors']['emp_id'][0]);
        $this->assertSame('B01-CSH-001', $snapshot['data']['emp_id']);
        $this->assertSame('', $snapshot['data']['pin']);
        $this->assertSame(1, RateLimiter::attempts('floor-login-emp:b01-csh-001'));
    }

    public function test_unknown_employee_returns_the_same_generic_error(): void
    {
        [$snapshot] = $this->submitPin('UNKNOWN-ID', '1234');

        $this->assertGuest();
        $this->assertSame('Invalid Employee ID or PIN.', $snapshot['memo']['errors']['emp_id'][0]);
        $this->assertSame('UNKNOWN-ID', $snapshot['data']['emp_id']);
        $this->assertSame('', $snapshot['data']['pin']);
    }

    public function test_http_login_rotates_session_and_clears_stale_destination_and_lock(): void
    {
        session()->start();
        $previousId = session()->getId();
        session(['url.intended' => '/admin', 'terminal_locked' => true]);

        $this->submitPin('B01-CSH-001', '1234');

        $this->assertNotSame($previousId, session()->getId());
        $this->assertNull(session('url.intended'));
        $this->assertNull(session('terminal_locked'));
        $this->assertSame('pin', session('auth_method'));
    }

    public static function invalidPinFormats(): array
    {
        return ['short' => ['123'], 'long' => ['12345'], 'letters' => ['abcd'], 'empty' => ['']];
    }

    #[DataProvider('invalidPinFormats')]
    public function test_invalid_pin_format_never_creates_a_session(string $pin): void
    {
        [$snapshot] = $this->submitPin('B01-CSH-001', $pin);

        $this->assertGuest();
        $this->assertArrayHasKey('pin', $snapshot['memo']['errors']);
        $this->assertNull(session('auth_method'));
    }

    public function test_inactive_employee_is_denied_over_http(): void
    {
        User::where('emp_id', 'B01-CSH-001')->update(['is_active' => false]);
        [$snapshot] = $this->submitPin('B01-CSH-001', '1234');

        $this->assertGuest();
        $this->assertStringContainsString('deactivated', $snapshot['memo']['errors']['emp_id'][0]);
        $this->assertSame('', $snapshot['data']['pin']);
    }

    public function test_inactive_branch_is_denied_over_http(): void
    {
        User::where('emp_id', 'B01-CSH-001')->firstOrFail()->branch->update(['is_active' => false]);
        [$snapshot] = $this->submitPin('B01-CSH-001', '1234');

        $this->assertGuest();
        $this->assertSame('Branch is currently inactive', $snapshot['memo']['errors']['emp_id'][0]);
        $this->assertSame('', $snapshot['data']['pin']);
    }

    public function test_http_lockout_blocks_correct_pin_until_window_expires(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->submitPin('B01-CSH-001', '0000', '192.0.2.' . $attempt);
            $this->assertSame($attempt, RateLimiter::attempts('floor-login-emp:b01-csh-001'));
        }

        [$snapshot] = $this->submitPin('B01-CSH-001', '1234', '192.0.2.100');
        $this->assertGuest();
        $this->assertSame(__('auth.throttle', ['seconds' => 60, 'minutes' => 1]), $snapshot['memo']['errors']['emp_id'][0]);

        $this->travel(61)->seconds();
        $this->submitPin('B01-CSH-001', '1234', '192.0.2.100');
        $this->assertAuthenticated();
    }

    public function test_logout_clears_pin_session_and_returns_to_guest_protection(): void
    {
        $this->submitPin('B01-CSH-001', '1234');
        $this->post('/logout')->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertNull(session('auth_method'));
        $this->assertNull(session('current_branch_id'));
        $this->get('/pos')->assertRedirect(route('login'));
        $this->get('/admin/login')->assertOk();
    }
}
