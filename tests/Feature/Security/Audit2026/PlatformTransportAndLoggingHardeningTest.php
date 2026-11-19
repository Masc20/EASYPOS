<?php

namespace Tests\Feature\Security\Audit2026;

use App\Domains\Branches\Models\Branch;
use App\Domains\Identity\Models\User;
use App\Domains\Menu\Actions\UpdateBranchPortionsAction;
use App\Domains\Menu\Livewire\MealCatalogTable;
use App\Domains\Menu\Models\Meal;
use App\Domains\Menu\Models\MealCategory;
use App\Domains\PointOfSale\Livewire\MealCatalog;
use App\Support\Enums\StaffRole;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Monolog\Level;
use Tests\TestCase;

class PlatformTransportAndLoggingHardeningTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch1;

    private User $cashier1;

    private User $chef1;

    private MealCategory $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Cache::flush();

        $this->branch1 = Branch::firstOrCreate(['code' => 'B01'], [
            'name' => 'Platform Branch 1',
            'is_active' => true,
        ]);

        $this->category = MealCategory::firstOrCreate(['slug' => 'platform-audit-cat'], [
            'name' => 'Platform Audit Cat',
            'is_active' => true,
        ]);

        $this->cashier1 = User::create([
            'name' => 'Platform Cashier',
            'email' => 'plt_c1@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'B01-CSH-PLT1',
            'pin_code' => Hash::make('5432'),
            'branch_id' => $this->branch1->id,
            'is_active' => true,
        ]);
        $this->cashier1->assignRole(StaffRole::CASHIER->value);

        $this->chef1 = User::create([
            'name' => 'Platform Chef',
            'email' => 'plt_chef1@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'B01-CHF-PLT1',
            'branch_id' => $this->branch1->id,
            'is_active' => true,
        ]);
        $this->chef1->assignRole(StaffRole::CHEF->value);
    }

    protected function tearDown(): void
    {
        Cache::flush();
        parent::tearDown();
    }

    public function test_vuln14_security_headers_enforce_hardened_csp_without_wildcard_connect_src(): void
    {
        foreach (['/login', '/admin/login'] as $uri) {
            $response = $this->get($uri);
            $response->assertStatus(200);

            $csp = (string) $response->headers->get('Content-Security-Policy');
            $this->assertNotEmpty($csp);
            $this->assertStringContainsString("default-src 'self'", $csp, "{$uri} CSP must define default-src 'self'.");
            $this->assertStringContainsString("object-src 'none'", $csp, "{$uri} CSP must define object-src 'none'.");
            $this->assertStringContainsString("base-uri 'self'", $csp, "{$uri} CSP must define base-uri 'self'.");
            $this->assertStringContainsString("form-action 'self'", $csp, "{$uri} CSP must define form-action 'self'.");
            $this->assertStringContainsString("connect-src 'self'", $csp, "{$uri} CSP must restrict connect-src to 'self'.");
            $this->assertDoesNotMatchRegularExpression(
                '/connect-src\s+[^;]*\bhttps:/i',
                $csp,
                "{$uri} CSP connect-src must NOT allow broad https: wildcard."
            );
        }
    }

    public function test_vuln14_admin_panel_attaches_throttle_admin_on_real_admin_login_route(): void
    {
        Cache::flush();

        for ($i = 1; $i <= 60; $i++) {
            $response = $this->get('/admin/login');
            $this->assertNotSame(429, $response->getStatusCode(), "Request #{$i} to /admin/login should not be throttled.");
        }

        $response61 = $this->get('/admin/login');
        $this->assertSame(
            429,
            $response61->getStatusCode(),
            '61st request within 1 minute to real /admin/login route must return 429 Too Many Requests.'
        );
    }

    public function test_vuln14_floor_pin_login_enforces_global_per_emp_id_rate_limit_across_rotating_ips(): void
    {
        Cache::flush();

        $loginPage = $this->get('/login');
        $loginPage->assertStatus(200);

        $matched = preg_match('/wire:snapshot="([^"]+)"/', $loginPage->getContent(), $matches);
        $this->assertSame(1, $matched, 'Could not locate Livewire snapshot on /login.');
        $snapshot = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5);
        $csrfToken = csrf_token();

        // 5 failed attempts from IP 10.0.0.1
        for ($i = 1; $i <= 5; $i++) {
            $res = $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
                ->withHeaders(['X-CSRF-TOKEN' => $csrfToken, 'X-Livewire' => 'true'])
                ->postJson('/livewire/update', [
                    '_token' => $csrfToken,
                    'components' => [[
                        'snapshot' => $snapshot,
                        'updates' => [
                            'emp_id' => $this->cashier1->emp_id,
                            'pin' => '0000',
                        ],
                        'calls' => [[
                            'path' => '',
                            'method' => 'login',
                            'params' => [],
                        ]],
                    ]],
                ]);

            $res->assertStatus(200);
            $snapshot = $res->json('components.0.snapshot') ?? $snapshot;
            $this->assertGuest();
        }

        // 6th attempt from a DIFFERENT IP (10.0.0.2) using the CORRECT PIN must still be blocked by global emp_id limiter
        $resNewIp = $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])
            ->withHeaders(['X-CSRF-TOKEN' => $csrfToken, 'X-Livewire' => 'true'])
            ->postJson('/livewire/update', [
                '_token' => $csrfToken,
                'components' => [[
                    'snapshot' => $snapshot,
                    'updates' => [
                        'emp_id' => $this->cashier1->emp_id,
                        'pin' => '5432',
                    ],
                    'calls' => [[
                        'path' => '',
                        'method' => 'login',
                        'params' => [],
                    ]],
                ]],
            ]);

        $resNewIp->assertStatus(200);
        $this->assertGuest();
        $snapshotMemo = json_decode((string) $resNewIp->json('components.0.snapshot'), true);
        $errorsPayload = $resNewIp->json('components.0.effects.errors')
            ?: ($snapshotMemo['memo']['errors'] ?? $resNewIp->json('components.0.effects.html') ?? []);
        $errorsJson = json_encode($errorsPayload);
        $this->assertStringContainsString('Too many', (string) $errorsJson);
    }

    public function test_vuln14_security_logging_channel_uses_info_level_even_when_log_level_is_warning(): void
    {
        $prevEnv = $_ENV['LOG_LEVEL'] ?? null;
        $prevServer = $_SERVER['LOG_LEVEL'] ?? null;
        $prevPutenv = getenv('LOG_LEVEL');

        try {
            $_ENV['LOG_LEVEL'] = 'warning';
            $_SERVER['LOG_LEVEL'] = 'warning';
            putenv('LOG_LEVEL=warning');
            Env::enablePutenv();

            $loadedConfig = require base_path('config/logging.php');
            $this->assertSame(
                'info',
                $loadedConfig['channels']['security']['level'] ?? null,
                'Security channel level must remain info even when LOG_LEVEL=warning.'
            );
        } finally {
            if ($prevEnv === null) {
                unset($_ENV['LOG_LEVEL']);
            } else {
                $_ENV['LOG_LEVEL'] = $prevEnv;
            }
            if ($prevServer === null) {
                unset($_SERVER['LOG_LEVEL']);
            } else {
                $_SERVER['LOG_LEVEL'] = $prevServer;
            }
            putenv($prevPutenv === false ? 'LOG_LEVEL' : "LOG_LEVEL={$prevPutenv}");
            Env::enablePutenv();
        }

        // Verify live Monolog logger accepts INFO (200) events like TERMINAL_LOCKED
        $securityLogger = Log::channel('security');
        $monolog = method_exists($securityLogger, 'getLogger') ? $securityLogger->getLogger() : null;
        if ($monolog && method_exists($monolog, 'getHandlers')) {
            foreach ($monolog->getHandlers() as $handler) {
                $this->assertLessThanOrEqual(
                    Level::Info->value,
                    $handler->getLevel()->value,
                    'Security channel handler must accept INFO level events so TERMINAL_LOCKED is never dropped.'
                );
            }
        }
    }

    public function test_vuln14_cors_config_trims_whitespace_and_filters_empty_allowed_origins(): void
    {
        $prevEnv = $_ENV['CORS_ALLOWED_ORIGINS'] ?? null;
        $prevServer = $_SERVER['CORS_ALLOWED_ORIGINS'] ?? null;
        $prevPutenv = getenv('CORS_ALLOWED_ORIGINS');

        try {
            $raw = ' https://pos.example.com , , https://admin.example.com , ';
            $_ENV['CORS_ALLOWED_ORIGINS'] = $raw;
            $_SERVER['CORS_ALLOWED_ORIGINS'] = $raw;
            putenv("CORS_ALLOWED_ORIGINS={$raw}");
            Env::enablePutenv();

            $cors = require base_path('config/cors.php');
            $this->assertSame(
                ['https://pos.example.com', 'https://admin.example.com'],
                $cors['allowed_origins']
            );
        } finally {
            if ($prevEnv === null) {
                unset($_ENV['CORS_ALLOWED_ORIGINS']);
            } else {
                $_ENV['CORS_ALLOWED_ORIGINS'] = $prevEnv;
            }
            if ($prevServer === null) {
                unset($_SERVER['CORS_ALLOWED_ORIGINS']);
            } else {
                $_SERVER['CORS_ALLOWED_ORIGINS'] = $prevServer;
            }
            putenv($prevPutenv === false ? 'CORS_ALLOWED_ORIGINS' : "CORS_ALLOWED_ORIGINS={$prevPutenv}");
            Env::enablePutenv();
        }
    }

    public function test_vuln15_guest_root_dashboard_does_not_disclose_framework_versions_or_stack(): void
    {
        $response = $this->get('/');
        $response->assertStatus(200);

        $response->assertDontSee('Laravel 12');
        $response->assertDontSee('Livewire 3');
        $response->assertDontSee('Filament 4');
        $response->assertDontSee('Spatie RBAC');
        $response->assertDontSee('Tailwind v4');
    }

    public function test_vuln15_meal_names_with_quotes_backslashes_and_newlines_render_safely_in_alpine_views(): void
    {
        $trickyName = "Chef's \\ \"Special\"\nLine2";

        $meal = Meal::create([
            'name' => $trickyName,
            'menu_code' => 'B01-SPC-99',
            'category_id' => $this->category->id,
            'branch_id' => $this->branch1->id,
            'price' => 199.00,
            'is_available' => true,
        ]);

        // 1. POS MealCatalog Blade view must not embed raw unescaped newlines inside single-quoted JS string literals
        $posCatalog = Livewire::actingAs($this->cashier1)->test(MealCatalog::class);
        $posHtml = $posCatalog->html();
        $this->assertDoesNotMatchRegularExpression(
            "/matches\([^)]*\n[^)]*\)/",
            $posHtml,
            'POS MealCatalog must use @js(...) so newlines in meal names do not break Alpine JS matches() expressions.'
        );
        $this->assertDoesNotMatchRegularExpression(
            "/add-to-cart-client',\s*\{[^}]*\n[^}]*\}/",
            $posHtml,
            'POS MealCatalog must use @js(...) so newlines in meal names do not break Alpine JS dispatch payload.'
        );

        // 2. Menu MealCatalogTable Blade view must not embed raw unescaped newlines inside openDelete(...)
        $menuTable = Livewire::actingAs($this->chef1)->test(MealCatalogTable::class);
        $menuHtml = $menuTable->html();
        $this->assertDoesNotMatchRegularExpression(
            "/openDelete\(\s*{$meal->id}\s*,[^)]*\n[^)]*\)/",
            $menuHtml,
            'Menu MealCatalogTable must use @js(...) so newlines/quotes in meal names do not break Alpine JS openDelete().'
        );
    }

    public function test_vuln15_update_branch_portions_does_not_pile_up_orphaned_broadcast_jobs_under_default_config(): void
    {
        $meal = Meal::create([
            'name' => 'Portion Broadcast Meal',
            'menu_code' => 'B01-BRD-01',
            'category_id' => $this->category->id,
            'branch_id' => $this->branch1->id,
            'price' => 120.00,
            'is_available' => true,
            'track_stock' => true,
            'stock_quantity' => 10,
        ]);

        Queue::fake();
        $this->actingAs($this->chef1);

        app(UpdateBranchPortionsAction::class)->handle($meal->id, true, 5);

        Queue::assertNotPushed(BroadcastEvent::class);
    }
}
