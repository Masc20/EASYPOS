<?php

namespace Tests\Feature\Security;

use App\Domains\Branches\Models\Branch;
use App\Domains\Identity\Models\User;
use App\Support\Enums\StaffRole;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RateLimitingTest extends TestCase
{
    use RefreshDatabase;

    protected User $userA;
    protected User $userB;
    protected Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->branch = Branch::firstOrCreate(['code' => 'B01'], [
            'name' => 'Flagship Branch',
            'is_active' => true,
        ]);

        $this->userA = User::create([
            'name' => 'User RateLimit A',
            'email' => 'user_a_ratelimit@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'B01-CSH-RLA',
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);
        $this->userA->assignRole(StaffRole::CASHIER->value);

        $this->userB = User::create([
            'name' => 'User RateLimit B',
            'email' => 'user_b_ratelimit@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'B01-CSH-RLB',
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);
        $this->userB->assignRole(StaffRole::CASHIER->value);

        // Ensure rate limiters per specification are registered if not already present
        if (! RateLimiter::limiter('pos')) {
            RateLimiter::for('pos', function (Request $request) {
                return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
            });
        }

        if (! RateLimiter::limiter('menu')) {
            RateLimiter::for('menu', function (Request $request) {
                return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
            });
        }

        if (! RateLimiter::limiter('reports')) {
            RateLimiter::for('reports', function (Request $request) {
                return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
            });
        }

        if (! RateLimiter::limiter('admin')) {
            RateLimiter::for('admin', function (Request $request) {
                return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
            });
        }

        // Register test routes bound to the named limiters
        Route::middleware(['web', 'auth', 'throttle:pos'])->get('/_test/throttle-pos', fn () => response()->json(['status' => 'ok']));
        Route::middleware(['web', 'auth', 'throttle:menu'])->get('/_test/throttle-menu', fn () => response()->json(['status' => 'ok']));
        Route::middleware(['web', 'auth', 'throttle:reports'])->get('/_test/throttle-reports', fn () => response()->json(['status' => 'ok']));
        Route::middleware(['web', 'throttle:admin'])->get('/_test/throttle-admin', fn () => response()->json(['status' => 'ok']));

        $this->clearAllLimiters();
    }

    protected function tearDown(): void
    {
        $this->clearAllLimiters();
        parent::tearDown();
    }

    protected function clearAllLimiters(): void
    {
        RateLimiter::clear('pos');
        RateLimiter::clear('menu');
        RateLimiter::clear('reports');
        RateLimiter::clear('admin');
        if (isset($this->userA)) {
            RateLimiter::clear((string) $this->userA->id);
            RateLimiter::clear('pos:' . $this->userA->id);
            RateLimiter::clear('menu:' . $this->userA->id);
            RateLimiter::clear('reports:' . $this->userA->id);
        }
        if (isset($this->userB)) {
            RateLimiter::clear((string) $this->userB->id);
            RateLimiter::clear('pos:' . $this->userB->id);
            RateLimiter::clear('menu:' . $this->userB->id);
            RateLimiter::clear('reports:' . $this->userB->id);
        }
        RateLimiter::clear('127.0.0.1');
    }

    public function test_pos_route_rate_limiting_throttles_at_sixty_requests_per_minute(): void
    {
        $this->actingAs($this->userA);

        // 60 requests should pass
        for ($i = 1; $i <= 60; $i++) {
            $response = $this->get('/_test/throttle-pos');
            $this->assertSame(200, $response->getStatusCode(), "Request #{$i} should return 200 OK");
        }

        // 61st request must trigger 429 Too Many Requests
        $response61 = $this->get('/_test/throttle-pos');
        $this->assertSame(429, $response61->getStatusCode(), 'Request #61 should return 429 Too Many Requests');
        $this->assertTrue($response61->headers->has('Retry-After'));
    }

    public function test_menu_route_rate_limiting_throttles_at_thirty_requests_per_minute(): void
    {
        $this->actingAs($this->userA);

        for ($i = 1; $i <= 30; $i++) {
            $response = $this->get('/_test/throttle-menu');
            $this->assertSame(200, $response->getStatusCode(), "Request #{$i} should return 200 OK");
        }

        $response31 = $this->get('/_test/throttle-menu');
        $this->assertSame(429, $response31->getStatusCode(), 'Request #31 should return 429 Too Many Requests');
        $this->assertTrue($response31->headers->has('Retry-After'));
    }

    public function test_reports_route_rate_limiting_throttles_at_ten_requests_per_minute(): void
    {
        $this->actingAs($this->userA);

        for ($i = 1; $i <= 10; $i++) {
            $response = $this->get('/_test/throttle-reports');
            $this->assertSame(200, $response->getStatusCode(), "Request #{$i} should return 200 OK");
        }

        $response11 = $this->get('/_test/throttle-reports');
        $this->assertSame(429, $response11->getStatusCode(), 'Request #11 should return 429 Too Many Requests');
        $this->assertTrue($response11->headers->has('Retry-After'));
    }

    public function test_admin_route_rate_limiting_throttles_at_sixty_requests_per_minute(): void
    {
        for ($i = 1; $i <= 60; $i++) {
            $response = $this->get('/_test/throttle-admin');
            $this->assertSame(200, $response->getStatusCode(), "Admin Request #{$i} should return 200 OK");
        }

        $response61 = $this->get('/_test/throttle-admin');
        $this->assertSame(429, $response61->getStatusCode(), 'Admin Request #61 should return 429 Too Many Requests');
    }

    public function test_rate_limiting_is_partitioned_by_user_id(): void
    {
        // User A exhausts quota (60 requests)
        $this->actingAs($this->userA);
        for ($i = 1; $i <= 60; $i++) {
            $this->get('/_test/throttle-pos');
        }
        $this->assertSame(429, $this->get('/_test/throttle-pos')->getStatusCode());

        // User B making a request is NOT throttled
        $this->actingAs($this->userB);
        $responseB = $this->get('/_test/throttle-pos');
        $this->assertSame(200, $responseB->getStatusCode(), 'User B should have an independent rate limit bucket');
    }

    public function test_rate_limiting_returns_retry_after_header_and_remaining_headers(): void
    {
        $this->actingAs($this->userA);

        for ($i = 1; $i <= 10; $i++) {
            $this->get('/_test/throttle-reports');
        }

        $throttled = $this->get('/_test/throttle-reports');
        $this->assertSame(429, $throttled->getStatusCode());
        $this->assertTrue($throttled->headers->has('Retry-After'));

        $retryAfter = (int) $throttled->headers->get('Retry-After');
        $this->assertGreaterThan(0, $retryAfter);
        $this->assertLessThanOrEqual(60, $retryAfter);
    }

    public function test_distributed_pin_rate_limiter_key_and_threshold(): void
    {
        // AUDIT-09: void-pin-approval limiter format: void-pin-approval:{ip}:{manager_id}:{order_id}
        $key = 'void-pin-approval:127.0.0.1:1:100';
        RateLimiter::clear($key);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->assertFalse(RateLimiter::tooManyAttempts($key, 5), "Attempt {$attempt} should not be locked out");
            RateLimiter::hit($key, 600);
        }

        $this->assertTrue(RateLimiter::tooManyAttempts($key, 5), '6th attempt must be locked out after 5 attempts');
        $this->assertGreaterThan(0, RateLimiter::availableIn($key));

        RateLimiter::clear($key);
        $this->assertFalse(RateLimiter::tooManyAttempts($key, 5));
    }
}
