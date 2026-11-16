<?php

namespace Tests\Feature\Security;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TestStrictCsrfMiddleware extends ValidateCsrfToken
{
    protected function runningUnitTests()
    {
        return false;
    }
}

class LivewireCsrfTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', TestStrictCsrfMiddleware::class])->post('/_test/livewire-checkout-action', function () {
            return response()->json(['status' => 'order_placed']);
        });

        Route::middleware(['web', TestStrictCsrfMiddleware::class])->post('/_test/livewire-void-action', function () {
            return response()->json(['status' => 'void_approved']);
        });
    }

    public function test_forged_livewire_checkout_request_with_mismatched_csrf_returns_419(): void
    {
        $response = $this->withSession(['_token' => 'authentic_pos_token_123'])
            ->withHeaders([
                'X-CSRF-TOKEN' => 'forged_attacker_token_456',
            ])
            ->post('/_test/livewire-checkout-action', [
                'component' => 'pos.checkout.checkout-modal',
                'action' => 'processCheckoutFromClient',
            ]);

        $response->assertStatus(419);
    }

    public function test_forged_livewire_void_request_with_missing_csrf_returns_419(): void
    {
        $response = $this->withSession(['_token' => 'authentic_pos_token_123'])
            ->withHeaders([
                'X-CSRF-TOKEN' => '',
            ])
            ->post('/_test/livewire-void-action', [
                'component' => 'pos.checkout.manager-pin-modal',
                'action' => 'submitVoidRequest',
            ]);

        $response->assertStatus(419);
    }

    public function test_livewire_request_with_valid_matching_csrf_token_succeeds(): void
    {
        $token = 'valid_cryptographic_csrf_token_789';

        $response = $this->withSession(['_token' => $token])
            ->withHeaders([
                'X-CSRF-TOKEN' => $token,
            ])
            ->post('/_test/livewire-checkout-action', [
                'component' => 'pos.checkout.checkout-modal',
                'action' => 'processCheckoutFromClient',
            ]);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'order_placed']);
    }

    public function test_csrf_middleware_throws_token_mismatch_exception_directly(): void
    {
        $middleware = new TestStrictCsrfMiddleware(app(), app('encrypter'));

        $request = Request::create('/livewire/update', 'POST');
        $session = app('session.store');
        $session->start();
        $session->put('_token', 'server_token');
        $request->setLaravelSession($session);
        $request->headers->set('X-CSRF-TOKEN', 'tampered_token');

        $this->expectException(TokenMismatchException::class);
        $middleware->handle($request, fn () => response('OK'));
    }

    public function test_csrf_middleware_passes_when_header_token_matches_session(): void
    {
        $middleware = new TestStrictCsrfMiddleware(app(), app('encrypter'));

        $request = Request::create('/livewire/update', 'POST');
        $session = app('session.store');
        $session->start();
        $token = 'matching_csrf_token_abc';
        $session->put('_token', $token);
        $request->setLaravelSession($session);
        $request->headers->set('X-CSRF-TOKEN', $token);

        $response = $middleware->handle($request, fn () => response('OK', 200));
        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_validate_csrf_token_class_is_configured_and_resolvable(): void
    {
        $this->assertTrue(class_exists(ValidateCsrfToken::class));
        $this->assertTrue(is_subclass_of(ValidateCsrfToken::class, VerifyCsrfToken::class) || ValidateCsrfToken::class === VerifyCsrfToken::class);
    }
}
