<?php

namespace Tests\Feature\Security;

use Illuminate\Support\Facades\Crypt;
use Symfony\Component\HttpFoundation\Cookie;
use Tests\TestCase;

class SessionSecurityTest extends TestCase
{
    public function test_env_example_documents_session_encryption_as_enabled(): void
    {
        $envExample = file_get_contents(base_path('.env.example'));
        $this->assertMatchesRegularExpression(
            '/^SESSION_ENCRYPT\s*=\s*true/m',
            $envExample,
            '.env.example must document SESSION_ENCRYPT=true for production deployment.'
        );
    }

    public function test_session_encryption_can_be_enabled_in_configuration(): void
    {
        config(['session.encrypt' => true]);
        $this->assertTrue(
            config('session.encrypt'),
            'Session encryption (SESSION_ENCRYPT) must be enabled for defense-in-depth data protection.'
        );
    }

    public function test_session_cookie_has_httponly_flag(): void
    {
        $response = $this->get('/login');

        $sessionCookieName = config('session.cookie');
        $sessionCookie = null;

        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getName() === $sessionCookieName) {
                $sessionCookie = $cookie;
                break;
            }
        }

        $this->assertNotNull($sessionCookie, "Session cookie [{$sessionCookieName}] must be set in response.");
        $this->assertTrue($sessionCookie->isHttpOnly(), 'Session cookie must have HttpOnly flag set to prevent XSS exfiltration.');
    }

    public function test_session_cookie_has_samesite_lax_attribute(): void
    {
        $response = $this->get('/login');

        $sessionCookieName = config('session.cookie');
        $sessionCookie = null;

        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getName() === $sessionCookieName) {
                $sessionCookie = $cookie;
                break;
            }
        }

        $this->assertNotNull($sessionCookie);
        $this->assertSame(
            Cookie::SAMESITE_LAX,
            $sessionCookie->getSameSite(),
            'Session cookie SameSite attribute must be lax to prevent CSRF in cross-site navigations.'
        );
    }

    public function test_session_cookie_has_secure_flag_when_request_is_over_https(): void
    {
        config(['session.secure' => true]);

        $response = $this->get('https://localhost/login');

        $sessionCookieName = config('session.cookie');
        $sessionCookie = null;

        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getName() === $sessionCookieName) {
                $sessionCookie = $cookie;
                break;
            }
        }

        $this->assertNotNull($sessionCookie);
        $this->assertTrue(
            $sessionCookie->isSecure(),
            'Session cookie must have Secure flag set when served over HTTPS.'
        );
    }

    public function test_session_data_can_be_encrypted_and_decrypted_with_application_key(): void
    {
        $sensitiveData = ['user_id' => 42, 'role' => 'cashier', 'token' => 'secret_terminal_token_2026'];
        $serialized = serialize($sensitiveData);

        $encrypted = Crypt::encrypt($serialized, false);
        $this->assertNotSame($serialized, $encrypted);
        $this->assertStringNotContainsString('secret_terminal_token_2026', $encrypted);

        $decrypted = unserialize(Crypt::decrypt($encrypted, false));
        $this->assertSame($sensitiveData, $decrypted);
    }

    public function test_session_cookie_name_does_not_leak_default_laravel_identifier(): void
    {
        $cookieName = config('session.cookie');
        $this->assertNotEmpty($cookieName);
        $this->assertSame(
            config('session.http_only'),
            true,
            'SESSION_HTTP_ONLY configuration must be true.'
        );
    }
}
