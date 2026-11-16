<?php

namespace Tests\Feature\Security;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class CorsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/api/test-cors', fn () => response()->json(['status' => 'ok']));
        Route::post('/api/test-cors', fn () => response()->json(['status' => 'created']));
    }

    public function test_cors_configuration_structure_and_allowed_origins(): void
    {
        $this->assertTrue(config('cors.supports_credentials'), 'CORS must support credentials for authenticated API sessions.');
        $this->assertContains('api/*', config('cors.paths'), 'CORS paths must cover api/* routes.');
        $this->assertContains(
            'https://easypos.dev',
            config('cors.allowed_origins'),
            'https://easypos.dev must be included in allowed_origins.'
        );
    }

    public function test_cors_preflight_allows_configured_whitelisted_origin(): void
    {
        $response = $this->call(
            'OPTIONS',
            '/api/test-cors',
            [],
            [],
            [],
            [
                'HTTP_ORIGIN' => 'https://easypos.dev',
                'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
                'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'Content-Type, X-Requested-With',
            ]
        );

        $this->assertContains($response->getStatusCode(), [200, 204]);
        $response->assertHeader('Access-Control-Allow-Origin', 'https://easypos.dev');
        $response->assertHeader('Access-Control-Allow-Credentials', 'true');
    }

    public function test_cors_preflight_rejects_untrusted_origin(): void
    {
        $response = $this->call(
            'OPTIONS',
            '/api/test-cors',
            [],
            [],
            [],
            [
                'HTTP_ORIGIN' => 'https://malicious-attacker.com',
                'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
                'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'Content-Type',
            ]
        );

        $allowOrigin = $response->headers->get('Access-Control-Allow-Origin');
        $this->assertNotSame(
            'https://malicious-attacker.com',
            $allowOrigin,
            'Untrusted cross-origin request must not receive Access-Control-Allow-Origin header.'
        );
    }

    public function test_cors_actual_request_from_whitelisted_origin_receives_allow_headers(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'https://easypos.dev',
        ])->getJson('/api/test-cors');

        $response->assertStatus(200);
        $response->assertHeader('Access-Control-Allow-Origin', 'https://easypos.dev');
        $response->assertHeader('Access-Control-Allow-Credentials', 'true');
    }

    public function test_cors_actual_request_from_untrusted_origin_does_not_receive_allow_headers(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'https://evil-hacker.com',
        ])->getJson('/api/test-cors');

        $response->assertStatus(200);
        $this->assertNotSame(
            'https://evil-hacker.com',
            $response->headers->get('Access-Control-Allow-Origin')
        );
    }

    public function test_cors_is_not_applied_to_standard_web_routes(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'https://easypos.dev',
        ])->get('/login');

        $response->assertStatus(200);
        $this->assertNull(
            $response->headers->get('Access-Control-Allow-Origin'),
            'Standard web routes like /login outside cors.paths must not have CORS headers attached.'
        );
    }
}
