<?php

namespace Tests\Unit;

use App\Domains\Branches\Models\Branch;
use App\Domains\Identity\Models\User;
use App\Domains\Menu\Models\Meal;
use App\Domains\Menu\Models\MealCategory;
use App\Support\Enums\KitchenTicketItemStatus;
use App\Support\InputSanitizer;
use App\Support\SecurityLogger;
use App\Support\Services\CodeGeneratorService;
use App\Support\SqlDialect;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Tests\TestCase;

class SupportHelpersTest extends TestCase
{
    use RefreshDatabase;

    public function test_input_sanitizer_strips_dangerous_blocks_and_tags_while_preserving_safe_characters(): void
    {
        $this->assertNull(InputSanitizer::clean(null));
        $this->assertNull(InputSanitizer::clean('   '));
        $this->assertNull(InputSanitizer::clean('<script>alert(1)</script>   '));
        $this->assertSame('Crispy Pata', InputSanitizer::clean("<script>alert('xss')</script>Crispy Pata"));
        $this->assertSame('Sizzling Sisig', InputSanitizer::clean('<style>body{display:none}</style><b>Sizzling</b> Sisig'));
        $this->assertSame('Flame-grilled patty', InputSanitizer::clean('<iframe src="https://evil.test"></iframe><p>Flame-grilled patty</p><img src=x onerror=alert(1)>'));
        $this->assertSame('Fish & Chips "Special" (100% Cod)', InputSanitizer::clean('  Fish & Chips "Special" (100% Cod)  '));
    }

    public function test_security_logger_routes_psr3_levels_and_swallows_channel_exceptions(): void
    {
        $logger = \Mockery::spy(LoggerInterface::class);
        Log::shouldReceive('channel')->with('security')->andReturn($logger);

        SecurityLogger::log('info', 'LOGIN_OK', ['user_id' => 1]);
        SecurityLogger::log('WARNING', 'PIN_FAILED', ['attempts' => 2]);
        SecurityLogger::log('unknown_level', 'FALLBACK_WARN', ['ip' => '127.0.0.1']);

        $logger->shouldHaveReceived('info')->with('LOGIN_OK', ['user_id' => 1])->once();
        $logger->shouldHaveReceived('warning')->with('PIN_FAILED', ['attempts' => 2])->once();
        $logger->shouldHaveReceived('warning')->with('FALLBACK_WARN', ['ip' => '127.0.0.1'])->once();

        // Must never throw when Log::channel('security') fails
        Log::shouldReceive('channel')->with('security')->andThrow(new RuntimeException('Permission denied on security.log'));
        SecurityLogger::log('critical', 'DISK_FAILURE_TEST', ['foo' => 'bar']);
        $this->assertTrue(true);
    }

    public function test_sql_dialect_generates_driver_specific_expressions_and_validates_columns(): void
    {
        $this->assertSame('INTEGER', SqlDialect::castType('sqlite'));
        $this->assertSame('INTEGER', SqlDialect::castType('pgsql'));
        $this->assertSame('UNSIGNED', SqlDialect::castType('mysql'));

        $this->assertSame("strftime('%Y-%m-%d', datetime(orders.created_at, '+8 hours'))", SqlDialect::periodExpression('orders.created_at', 'day', 'sqlite'));
        $this->assertSame("strftime('%Y-%m', datetime(orders.created_at, '+8 hours'))", SqlDialect::periodExpression('orders.created_at', 'month', 'sqlite'));
        $this->assertSame("strftime('%Y', datetime(orders.created_at, '+8 hours'))", SqlDialect::periodExpression('orders.created_at', 'year', 'sqlite'));

        $this->assertSame("TO_CHAR((orders.created_at + INTERVAL '8 hours'), 'YYYY-MM-DD')", SqlDialect::periodExpression('orders.created_at', 'day', 'pgsql'));
        $this->assertSame("TO_CHAR((orders.created_at + INTERVAL '8 hours'), 'IYYY-\"W\"IW')", SqlDialect::periodExpression('orders.created_at', 'week', 'pgsql'));
        $this->assertSame("DATE_FORMAT(DATE_ADD(orders.created_at, INTERVAL 8 HOUR), '%Y-%m')", SqlDialect::periodExpression('orders.created_at', 'month', 'mysql'));
        $this->assertSame("DATE(DATE_ADD(orders.created_at, INTERVAL 8 HOUR))", SqlDialect::periodExpression('orders.created_at', 'day', 'mysql'));

        $this->assertSame("CAST(strftime('%H', datetime(created_at, '+8 hours')) AS INTEGER)", SqlDialect::hourExpression('created_at', 'sqlite'));
        $this->assertSame("EXTRACT(HOUR FROM (orders.created_at + INTERVAL '8 hours'))::INTEGER", SqlDialect::hourExpression('orders.created_at', 'pgsql'));
        $this->assertSame("HOUR(DATE_ADD(orders.created_at, INTERVAL 8 HOUR))", SqlDialect::hourExpression('orders.created_at', 'mysql'));

        $invalidColumns = [
            'created_at); DROP TABLE orders;--',
            "orders.created_at\n",
            "orders.created_at\n--",
            'orders.created_at ',
            'a.b.c',
            '1bad_col',
            '',
        ];

        foreach ($invalidColumns as $badColumn) {
            try {
                SqlDialect::periodExpression($badColumn, 'day', 'sqlite');
                $this->fail('Expected InvalidArgumentException for periodExpression column: '.json_encode($badColumn));
            } catch (InvalidArgumentException) {
                $this->assertTrue(true);
            }

            try {
                SqlDialect::hourExpression($badColumn, 'sqlite');
                $this->fail('Expected InvalidArgumentException for hourExpression column: '.json_encode($badColumn));
            } catch (InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_kitchen_ticket_item_status_enum_values_and_labels(): void
    {
        $this->assertSame(['pending', 'cooking', 'done'], KitchenTicketItemStatus::values());
        $this->assertSame('Pending', KitchenTicketItemStatus::PENDING->label());
        $this->assertSame('Cooking', KitchenTicketItemStatus::COOKING->label());
        $this->assertSame('Done', KitchenTicketItemStatus::DONE->label());
    }

    public function test_code_generator_service_handles_fallback_prefixes_and_collisions(): void
    {
        $branch = Branch::create(['name' => 'Alpha Beta', 'code' => 'B01', 'is_active' => true]);

        // Fallback role prefix when unknown role name contains symbols
        $customEmpId = CodeGeneratorService::generateEmpId($branch->id, '12345');
        $this->assertSame('B01-STF-001', $customEmpId);

        User::create([
            'name' => 'Staff Collision',
            'emp_id' => 'B01-STF-001',
            'branch_id' => $branch->id,
            'pin_code' => Hash::make('1234'),
        ]);
        $this->assertSame('B01-STF-002', CodeGeneratorService::generateEmpId($branch->id, '12345'));

        // Category with non-alpha name falls back to X-001
        $cat = MealCategory::create(['name' => '123!', 'slug' => 'num-cat', 'is_active' => true]);
        $this->assertSame('X-001', CodeGeneratorService::generateMenuCode($cat->id));

        Meal::create([
            'category_id' => $cat->id,
            'name' => 'Number Dish',
            'menu_code' => 'X-001',
            'price' => 50.00,
        ]);
        $this->assertSame('X-002', CodeGeneratorService::generateMenuCode($cat->id));

        // Sequential B{NN} fallback when branch name is empty or colliding
        $seqBranch = CodeGeneratorService::generateBranchCode('');
        $this->assertSame('B02', $seqBranch);
    }
}
