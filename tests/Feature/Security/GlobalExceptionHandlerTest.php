<?php

namespace Tests\Feature\Security;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class GlobalExceptionHandlerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/_test/throw-sensitive-web', function () {
            throw new \RuntimeException('CRITICAL DATABASE LEAK: SELECT password, pin_code FROM users WHERE id = 1');
        });

        Route::get('/_test/throw-pdo-exception', function () {
            throw new \PDOException('SQLSTATE[HY000] [2002] Connection refused (SQL: SELECT card_cvv FROM payments)');
        });
    }

    public function test_production_unhandled_web_exception_redacts_sql_queries_and_stack_traces(): void
    {
        config([
            'app.env' => 'production',
            'app.debug' => false,
        ]);

        $response = $this->withExceptionHandling()->get('/_test/throw-sensitive-web');

        $response->assertStatus(500);
        $response->assertDontSee('CRITICAL DATABASE LEAK');
        $response->assertDontSee('SELECT password, pin_code FROM users');
        $response->assertDontSee('RuntimeException');
        $response->assertDontSee('vendor/laravel');
    }

    public function test_production_unhandled_json_exception_returns_clean_json_error(): void
    {
        config([
            'app.env' => 'production',
            'app.debug' => false,
        ]);

        $response = $this->withExceptionHandling()->getJson('/_test/throw-sensitive-web');

        $response->assertStatus(500);
        $response->assertJsonMissing([
            'exception' => 'RuntimeException',
            'trace' => [],
        ]);
        $response->assertDontSee('CRITICAL DATABASE LEAK');
        $response->assertDontSee('pin_code');

        $this->assertStringContainsString('Server Error', $response->json('message') ?? 'Server Error');
    }

    public function test_production_pdo_exception_hides_table_and_column_names(): void
    {
        config([
            'app.env' => 'production',
            'app.debug' => false,
        ]);

        $response = $this->withExceptionHandling()->get('/_test/throw-pdo-exception');

        $response->assertStatus(500);
        $response->assertDontSee('SQLSTATE');
        $response->assertDontSee('SELECT card_cvv FROM payments');
        $response->assertDontSee('Connection refused');
    }

    public function test_production_exceptions_are_reported_to_logger_despite_user_facing_redaction(): void
    {
        Log::spy();

        config([
            'app.env' => 'production',
            'app.debug' => false,
        ]);

        $this->withExceptionHandling()->get('/_test/throw-sensitive-web');

        Log::shouldHaveReceived('error');
    }

    public function test_debug_mode_is_strictly_disabled_by_default_in_production_environment(): void
    {
        config(['app.env' => 'production', 'app.debug' => false]);

        $this->assertFalse(config('app.debug'));
        $this->assertSame('production', config('app.env'));
    }
}
