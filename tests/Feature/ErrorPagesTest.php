<?php

namespace Tests\Feature;

use App\Domains\Identity\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        // Register temporary test routes to verify error code rendering
        Route::get('/test-error/{code}', function ($code) {
            abort((int) $code);
        });
    }

    public function test_404_error_page_renders_with_brand_layout(): void
    {
        $response = $this->get('/non-existent-route-for-testing-404');

        $response->assertStatus(404);
        $response->assertSee('HTTP 404');
        $response->assertSee('Page or Resource Not Found');
        $response->assertSee('EASYPOS');
        $response->assertSee('Floor Staff Login');
    }

    public function test_403_error_page_renders_with_station_isolation_context(): void
    {
        $cashier = User::where('emp_id', 'B01-CSH-001')->first();

        $response = $this->actingAs($cashier)->get('/inventory');

        $response->assertStatus(403);
        $response->assertSee('HTTP 403');
        $response->assertSee('Station Access Restricted');
        $response->assertSee('Floor staff terminals are isolated');
        $response->assertSee('Return to POS Register Terminal');
    }

    public function test_400_bad_request_page_renders(): void
    {
        $response = $this->get('/test-error/400');

        $response->assertStatus(400);
        $response->assertSee('HTTP 400');
        $response->assertSee('Invalid Request Payload');
    }

    public function test_401_unauthorized_page_renders(): void
    {
        $response = $this->get('/test-error/401');

        $response->assertStatus(401);
        $response->assertSee('HTTP 401');
        $response->assertSee('Staff Authentication Required');
    }

    public function test_419_session_expired_page_renders(): void
    {
        $response = $this->get('/test-error/419');

        $response->assertStatus(419);
        $response->assertSee('HTTP 419');
        $response->assertSee('Reload & Resume Session');
    }

    public function test_429_too_many_requests_page_renders(): void
    {
        $response = $this->get('/test-error/429');

        $response->assertStatus(429);
        $response->assertSee('HTTP 429');
        $response->assertSee('Terminal Rate Limit Exceeded');
    }

    public function test_500_server_error_page_renders(): void
    {
        $response = $this->get('/test-error/500');

        $response->assertStatus(500);
        $response->assertSee('HTTP 500');
        $response->assertSee('Internal Server Exception');
    }

    public function test_503_maintenance_page_renders(): void
    {
        $response = $this->get('/test-error/503');

        $response->assertStatus(503);
        $response->assertSee('HTTP 503');
        $response->assertSee('Terminal Maintenance in Progress');
    }

    public function test_4xx_fallback_renders_for_unmapped_client_errors(): void
    {
        $response = $this->get('/test-error/405');

        $response->assertStatus(405);
        $response->assertSee('HTTP 405');
        $response->assertSee('Terminal Request Exception');
    }

    public function test_5xx_fallback_renders_for_unmapped_server_errors(): void
    {
        $response = $this->get('/test-error/502');

        $response->assertStatus(502);
        $response->assertSee('HTTP 502');
        $response->assertSee('Server Infrastructure Fault');
    }

    public function test_error_page_respects_dark_mode_cookie(): void
    {
        $response = $this->withUnencryptedCookie('theme', 'dark')->get('/non-existent-route-dark-test');

        $response->assertStatus(404);
        $response->assertSee('class="dark"', false);
    }
}
