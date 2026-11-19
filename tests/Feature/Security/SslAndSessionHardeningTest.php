<?php

namespace Tests\Feature\Security;

use App\Domains\Branches\Models\Branch;
use App\Domains\Identity\Models\User;
use App\Support\Constants\SystemConstants;
use App\Support\Enums\StaffRole;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SslAndSessionHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->branch = Branch::firstOrCreate(['code' => 'B01'], [
            'name' => 'Flagship Branch',
            'is_active' => true,
        ]);
    }

    public function test_untrusted_proxy_cannot_trigger_hsts_header_via_x_forwarded_proto(): void
    {
        $response = $this->withServerVariables([
            'REMOTE_ADDR' => '203.0.113.195',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTPS' => 'off',
        ])->get('http://localhost/login');

        $response->assertStatus(200);
        $response->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_trusted_vpc_proxy_triggers_hsts_header_when_x_forwarded_proto_is_https(): void
    {
        $response = $this->withServerVariables([
            'REMOTE_ADDR' => '10.0.1.50',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_PORT' => '443',
        ])->get('/login');

        $response->assertStatus(200);
        $response->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
    }

    public function test_session_secure_cookie_defaults_to_true_in_production_and_false_in_testing(): void
    {
        $prevAppEnv = $_ENV['APP_ENV'] ?? null;
        $prevServerAppEnv = $_SERVER['APP_ENV'] ?? null;
        $prevSecureCookie = $_ENV['SESSION_SECURE_COOKIE'] ?? null;
        $prevServerSecureCookie = $_SERVER['SESSION_SECURE_COOKIE'] ?? null;

        try {
            unset($_ENV['SESSION_SECURE_COOKIE'], $_SERVER['SESSION_SECURE_COOKIE']);
            putenv('SESSION_SECURE_COOKIE');

            $_ENV['APP_ENV'] = 'production';
            $_SERVER['APP_ENV'] = 'production';
            putenv('APP_ENV=production');
            $prodConfig = require base_path('config/session.php');
            $this->assertTrue($prodConfig['secure']);

            $_ENV['APP_ENV'] = 'testing';
            $_SERVER['APP_ENV'] = 'testing';
            putenv('APP_ENV=testing');
            $testConfig = require base_path('config/session.php');
            $this->assertFalse($testConfig['secure']);
        } finally {
            if ($prevAppEnv !== null) {
                $_ENV['APP_ENV'] = $prevAppEnv;
                putenv("APP_ENV={$prevAppEnv}");
            } else {
                unset($_ENV['APP_ENV']);
                putenv('APP_ENV');
            }

            if ($prevServerAppEnv !== null) {
                $_SERVER['APP_ENV'] = $prevServerAppEnv;
            } else {
                unset($_SERVER['SESSION_SECURE_COOKIE']);
            }

            if ($prevSecureCookie !== null) {
                $_ENV['SESSION_SECURE_COOKIE'] = $prevSecureCookie;
                putenv("SESSION_SECURE_COOKIE={$prevSecureCookie}");
            }

            if ($prevServerSecureCookie !== null) {
                $_SERVER['SESSION_SECURE_COOKIE'] = $prevServerSecureCookie;
            }
        }
    }

    public function test_env_production_and_env_example_enforce_cookie_bcrypt_and_cors_hardening(): void
    {
        $envProd = file_get_contents(base_path('.env.production'));
        $envExample = file_get_contents(base_path('.env.example'));

        $this->assertMatchesRegularExpression('/^BCRYPT_ROUNDS=13$/m', $envProd);
        $this->assertMatchesRegularExpression('/^SESSION_ENCRYPT=true$/m', $envProd);
        $this->assertMatchesRegularExpression('/^SESSION_SECURE_COOKIE=true$/m', $envProd);
        $this->assertMatchesRegularExpression('/^SESSION_HTTP_ONLY=true$/m', $envProd);
        $this->assertMatchesRegularExpression('/^SESSION_SAME_SITE=lax$/m', $envProd);
        $this->assertMatchesRegularExpression('/^CORS_ALLOWED_ORIGINS=https:\/\/easypos\.dev$/m', $envProd);
        $this->assertMatchesRegularExpression('/^APP_KEY=<replace-with-generated-app-key>$/m', $envProd);
        $this->assertMatchesRegularExpression('/^DB_PASSWORD=<replace-with-rds-password>$/m', $envProd);

        $this->assertMatchesRegularExpression('/^SESSION_ENCRYPT=true$/m', $envExample);
        $this->assertMatchesRegularExpression('/^SESSION_SECURE_COOKIE=true$/m', $envExample);
        $this->assertMatchesRegularExpression('/^SESSION_HTTP_ONLY=true$/m', $envExample);
        $this->assertMatchesRegularExpression('/^SESSION_SAME_SITE=lax$/m', $envExample);
        $this->assertMatchesRegularExpression('/^CORS_ALLOWED_ORIGINS=https:\/\/easypos\.dev$/m', $envExample);
    }

    public function test_deactivated_floor_user_is_logged_out_and_session_invalidated_across_floor_and_livewire_routes(): void
    {
        $cashier = User::create([
            'name' => 'Deactivated Cashier',
            'email' => null,
            'password' => null,
            'emp_id' => 'B01-CSH-D01',
            'branch_id' => $this->branch->id,
            'pin_code' => Hash::make('1234'),
            'is_active' => false,
        ]);
        $cashier->assignRole(StaffRole::CASHIER->value);

        $response = $this->actingAs($cashier)
            ->withSession([SystemConstants::SESSION_AUTH_METHOD => 'pin'])
            ->get('/pos');

        $response->assertRedirect(route('login'));
        $this->assertGuest();

        $livewireResponse = $this->actingAs($cashier)
            ->withSession([SystemConstants::SESSION_AUTH_METHOD => 'pin'])
            ->withHeaders(['X-Livewire' => 'true'])
            ->post('/livewire/update', []);

        $livewireResponse->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_deactivated_admin_user_is_logged_out_and_redirected_to_admin_login(): void
    {
        $manager = User::create([
            'name' => 'Deactivated Manager',
            'email' => 'deactivated-mgr@easypos.test',
            'password' => Hash::make('Str0ng!Pass#2026'),
            'emp_id' => 'B01-MGR-D01',
            'branch_id' => $this->branch->id,
            'is_active' => false,
        ]);
        $manager->assignRole(StaffRole::BRANCH_MANAGER->value);

        $response = $this->actingAs($manager)->get('/admin');

        $response->assertRedirect('/admin/login');
        $this->assertGuest();

        $jsonResponse = $this->actingAs($manager)
            ->getJson('/admin');

        $jsonResponse->assertStatus(401);
        $this->assertGuest();
    }

    public function test_active_pin_manager_still_receives_403_on_admin_panel(): void
    {
        $manager = User::where('emp_id', 'B01-MGR-001')->firstOrFail();

        $response = $this->actingAs($manager)
            ->withSession([SystemConstants::SESSION_AUTH_METHOD => 'pin'])
            ->get('/admin');

        $response->assertStatus(403);
        $this->assertAuthenticatedAs($manager);
    }

    public function test_app_shell_and_admin_panel_isolate_spa_prefetch_and_filament_error_notifications(): void
    {
        $cashier = User::where('emp_id', 'B01-CSH-001')->firstOrFail();
        $posResponse = $this->actingAs($cashier)
            ->withSession([SystemConstants::SESSION_AUTH_METHOD => 'pin'])
            ->get('/pos');

        $posResponse->assertStatus(200);
        $posResponse->assertSee('delete window.filamentErrorNotifications', false);
        $posResponse->assertSee('EASYPOS_BEGIN_LOGOUT', false);
        $posResponse->assertDontSee('wire:navigate', false);

        $this->flushSession();

        $owner = User::where('email', 'owner@easypos.com')->firstOrFail();
        $adminResponse = $this->actingAs($owner)->get('/admin');

        $adminResponse->assertStatus(200);
        $adminResponse->assertSee('/admin/logout', false);
        $adminResponse->assertDontSee('terminal_lock', false);
    }

    public function test_deactivated_branch_blocks_branch_manager_login_and_revokes_active_admin_session(): void
    {
        $manager = User::where('emp_id', 'B01-MGR-001')->firstOrFail();
        $branch = $manager->branch;
        $this->assertNotNull($branch);

        $branch->update(['is_active' => false]);
        $manager->unsetRelation('branch');

        // 1. User::canAccessAdmin() returns false when assigned branch is inactive
        $this->assertFalse($manager->canAccessAdmin(), 'Branch Manager of an inactive branch must not pass canAccessAdmin().');

        // 2. Back-office Filament login (/admin/login) rejects the Branch Manager
        \Livewire\Livewire::test(\App\Filament\Pages\Auth\Login::class)
            ->fillForm([
                'email' => $manager->email,
                'password' => 'password',
            ])
            ->call('authenticate')
            ->assertHasFormErrors(['email']);

        $this->assertGuest();

        // 3. If the Branch Manager had an existing session when the branch was deactivated, EnsureUserIsActive logs them out
        $response = $this->actingAs($manager)->get('/admin');
        $response->assertRedirect(url('/admin/login'));
        $this->assertGuest();

        // Restore branch active status
        $branch->update(['is_active' => true]);
    }
}
