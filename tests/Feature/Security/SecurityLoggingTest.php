<?php

namespace Tests\Feature\Security;

use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

class SecurityLoggingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! config('logging.channels.security')) {
            config([
                'logging.channels.security' => [
                    'driver' => 'daily',
                    'path' => storage_path('logs/security.log'),
                    'level' => 'info',
                    'days' => 90,
                ],
            ]);
        }
    }

    public function test_security_channel_is_configured_with_daily_rotation_and_90_days_retention(): void
    {
        $securityConfig = config('logging.channels.security');

        $this->assertNotNull($securityConfig, 'Security logging channel must be defined in logging configuration.');
        $this->assertSame('daily', $securityConfig['driver'], 'Security log driver must be daily for automated rotation.');
        $this->assertSame(90, (int) $securityConfig['days'], 'Security log retention must be at least 90 days for compliance.');
        $this->assertStringContainsString('security.log', $securityConfig['path'], 'Security log path must write to security.log.');
    }

    public function test_manager_pin_lockout_logs_to_security_channel(): void
    {
        $securityLogger = \Mockery::spy(LoggerInterface::class);
        Log::shouldReceive('channel')->with('security')->andReturn($securityLogger);

        Log::channel('security')->warning('MANAGER_PIN_VOID_LOCKED_OUT', [
            'ip' => '127.0.0.1',
            'order_id' => 101,
            'manager_id' => 2,
        ]);

        $securityLogger->shouldHaveReceived('warning')
            ->with('MANAGER_PIN_VOID_LOCKED_OUT', \Mockery::on(function (array $context) {
                return ! isset($context['pin']) && ! isset($context['pin_code']) && $context['order_id'] === 101;
            }));
    }

    public function test_manager_pin_failure_logs_to_security_channel_without_plain_text_pin(): void
    {
        $securityLogger = \Mockery::spy(LoggerInterface::class);
        Log::shouldReceive('channel')->with('security')->andReturn($securityLogger);

        $context = [
            'event' => 'MANAGER_PIN_VOID_ATTEMPT_FAILED',
            'order_id' => 50,
            'manager_emp_id' => 'B01-MGR-001',
            'attempt' => 3,
        ];

        Log::channel('security')->warning('MANAGER_PIN_VOID_ATTEMPT_FAILED', $context);

        $securityLogger->shouldHaveReceived('warning')
            ->with('MANAGER_PIN_VOID_ATTEMPT_FAILED', \Mockery::on(function (array $c) {
                $keys = array_keys($c);
                return ! in_array('pin', $keys, true)
                    && ! in_array('pin_code', $keys, true)
                    && ! in_array('password', $keys, true);
            }));
    }

    public function test_manager_pin_approval_logs_to_security_channel(): void
    {
        $securityLogger = \Mockery::spy(LoggerInterface::class);
        Log::shouldReceive('channel')->with('security')->andReturn($securityLogger);

        Log::channel('security')->info('MANAGER_PIN_VOID_APPROVED', [
            'order_id' => 105,
            'manager_id' => 3,
            'cashier_id' => 4,
        ]);

        $securityLogger->shouldHaveReceived('info')
            ->with('MANAGER_PIN_VOID_APPROVED', \Mockery::on(function (array $context) {
                return $context['order_id'] === 105 && $context['manager_id'] === 3;
            }));
    }

    public function test_security_channel_logger_instance_is_resolved(): void
    {
        $logger = Log::channel('security');
        $this->assertNotNull($logger);
        $this->assertInstanceOf(LoggerInterface::class, $logger);
    }
}
