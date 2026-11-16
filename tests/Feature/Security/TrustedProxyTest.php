<?php

namespace Tests\Feature\Security;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TrustedProxyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('web')->get('/_test/proxy-ip', function (Request $request) {
            return response()->json([
                'ip' => $request->ip(),
                'is_secure' => $request->isSecure(),
                'scheme' => $request->getScheme(),
            ]);
        });
    }

    public function test_untrusted_client_ip_cannot_spoof_client_ip_via_x_forwarded_for(): void
    {
        // 203.0.113.195 is an untrusted public IP trying to claim it's 10.0.0.1
        $response = $this->withServerVariables([
            'REMOTE_ADDR' => '203.0.113.195',
            'HTTP_X_FORWARDED_FOR' => '10.0.0.1',
        ])->get('/_test/proxy-ip');

        $response->assertStatus(200);
        $response->assertJson([
            'ip' => '203.0.113.195',
        ]);
        $this->assertNotSame('10.0.0.1', $response->json('ip'));
    }

    public function test_https_proto_from_untrusted_proxy_is_ignored(): void
    {
        // Untrusted proxy sending HTTP request claiming to be HTTPS
        $response = $this->withServerVariables([
            'REMOTE_ADDR' => '203.0.113.195',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTPS' => 'off',
        ])->get('/_test/proxy-ip');

        $response->assertStatus(200);
        $this->assertFalse($response->json('is_secure'));
    }

    public function test_trusted_aws_alb_cidr_10_correctly_resolves_forwarded_client_ip(): void
    {
        // 10.0.1.50 is inside AWS VPC CIDR 10.0.0.0/8
        $response = $this->withServerVariables([
            'REMOTE_ADDR' => '10.0.1.50',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.50',
        ])->get('/_test/proxy-ip');

        $response->assertStatus(200);
        $response->assertJson([
            'ip' => '203.0.113.50',
        ]);
    }

    public function test_trusted_172_cidr_proxy_correctly_resolves_forwarded_client_ip(): void
    {
        // 172.16.1.10 is inside AWS VPC CIDR 172.16.0.0/12
        $response = $this->withServerVariables([
            'REMOTE_ADDR' => '172.16.1.10',
            'HTTP_X_FORWARDED_FOR' => '198.51.100.42',
        ])->get('/_test/proxy-ip');

        $response->assertStatus(200);
        $response->assertJson([
            'ip' => '198.51.100.42',
        ]);
    }

    public function test_trusted_192_168_cidr_proxy_correctly_resolves_forwarded_client_ip(): void
    {
        // 192.168.1.100 is inside private CIDR 192.168.0.0/16
        $response = $this->withServerVariables([
            'REMOTE_ADDR' => '192.168.1.100',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.77',
        ])->get('/_test/proxy-ip');

        $response->assertStatus(200);
        $response->assertJson([
            'ip' => '203.0.113.77',
        ]);
    }

    public function test_https_proto_from_trusted_proxy_is_respected(): void
    {
        // 10.0.1.50 terminates TLS at AWS ALB and forwards X-Forwarded-Proto
        $response = $this->withServerVariables([
            'REMOTE_ADDR' => '10.0.1.50',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_PORT' => '443',
        ])->get('/_test/proxy-ip');

        $response->assertStatus(200);
        $this->assertTrue($response->json('is_secure'));
    }
}
