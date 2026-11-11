<?php

namespace Tests\Feature\Adversarial;

use App\Domains\Branches\Models\Branch;
use App\Domains\Identity\Models\User;
use App\Support\Enums\StaffRole;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class AdversarialErrorViewSanitizationTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branch;
    protected User $cashier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->branch = Branch::firstOrCreate(['code' => 'B01'], ['name' => 'Flagship Manila', 'is_active' => true]);

        $this->cashier = User::create([
            'name' => 'Secret Cashier Identity',
            'email' => 'leak_csh@easypos.test',
            'password' => Hash::make('super_secret_password_123'),
            'emp_id' => 'B01-CSH-LEAK',
            'pin_code' => Hash::make('7890'),
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);
        $this->cashier->assignRole(StaffRole::CASHIER->value);

        // Register a diagnostic route that throws an exception laden with sensitive markers
        Route::get('/_adversarial/trigger-500', function () {
            throw new RuntimeException(
                "SQLSTATE[42S02]: Base table not found: 1146 Table 'easypos.secret_payroll' doesn't exist " .
                "(Connection: mysql, SQL: SELECT salary, ssn, pin_code, password FROM secret_payroll WHERE emp_id = 'B01-CSH-LEAK') " .
                "in file C:\\xampp\\htdocs\\EASYPOS\\app\\Domains\\Identity\\SecretController.php on line 123"
            );
        });
    }

    public function test_production_500_does_not_leak_sql_queries_file_paths_or_staff_secrets(): void
    {
        Config::set('app.debug', false);

        $response = $this->actingAs($this->cashier)->get('/_adversarial/trigger-500');

        $response->assertStatus(500);

        $content = $response->getContent();

        // 1. Verify SQL queries are not leaked
        $this->assertStringNotContainsString('secret_payroll', $content);
        $this->assertStringNotContainsString('SELECT salary', $content);
        $this->assertStringNotContainsString('SQLSTATE', $content);

        // 2. Verify filesystem paths are not leaked
        $this->assertStringNotContainsString('C:\\xampp\\htdocs\\EASYPOS', $content);
        $this->assertStringNotContainsString('SecretController.php', $content);
        $this->assertStringNotContainsString('app/Domains', $content);

        // 3. Verify sensitive staff attributes are not leaked
        $this->assertStringNotContainsString('super_secret_password_123', $content);
        $this->assertStringNotContainsString('7890', $content);

        // 4. Verify generic user-safe error template is rendered
        $this->assertStringContainsString('500', $content);
        $this->assertStringContainsString('Internal Server Exception', $content);
    }

    public function test_production_404_does_not_leak_system_diagnostics(): void
    {
        Config::set('app.debug', false);

        $response = $this->actingAs($this->cashier)->get('/non-existent-adversarial-route-12345');

        $response->assertStatus(404);

        $content = $response->getContent();

        $this->assertStringNotContainsString('NotFoundHttpException', $content);
        $this->assertStringNotContainsString('vendor/laravel', $content);
        $this->assertStringNotContainsString('C:\\xampp\\htdocs', $content);
        $this->assertStringContainsString('404', $content);
        $this->assertStringContainsString('Page or Resource Not Found', $content);
    }

    public function test_production_403_renders_clean_station_notice(): void
    {
        Config::set('app.debug', false);

        // Cashier accesses admin panel
        $response = $this->actingAs($this->cashier)->get('/admin');

        $response->assertStatus(403);

        $content = $response->getContent();

        $this->assertStringNotContainsString('AccessDeniedHttpException', $content);
        $this->assertStringNotContainsString('C:\\xampp\\htdocs', $content);
        $this->assertStringContainsString('403', $content);
        $this->assertStringContainsString('Station Access Restricted', $content);
    }
}
