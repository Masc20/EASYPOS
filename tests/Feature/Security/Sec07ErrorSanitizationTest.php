<?php

namespace Tests\Feature\Security;

use App\Domains\Identity\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class Sec07ErrorSanitizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.debug' => false]);
    }

    public function test_production_500_response_redacts_sql_queries_and_filesystem_paths(): void
    {
        $sqlMarker = "SELECT private_column FROM secret_payroll WHERE salary > 99999";
        $pathMarker = "C:\\xampp\\htdocs\\EASYPOS\\app\\Confidential\\SensitiveFile.php";
        $secretKeyMarker = "base64:ConfidentialSecretTokenThatMustNeverLeak";

        Route::get('/_test_security/trigger-sensitive-exception', function () use ($sqlMarker, $pathMarker, $secretKeyMarker) {
            throw new RuntimeException("Database error with query {$sqlMarker} in file {$pathMarker} using key {$secretKeyMarker}");
        });

        $response = $this->get('/_test_security/trigger-sensitive-exception');

        $response->assertStatus(500);

        // Assert strictly that none of the sensitive diagnostic markers leak into the client HTML
        $response->assertDontSee($sqlMarker, false);
        $response->assertDontSee($pathMarker, false);
        $response->assertDontSee($secretKeyMarker, false);
        $response->assertDontSee('secret_payroll', false);
        $response->assertDontSee('SensitiveFile.php', false);

        // Assert generic user-safe error message is rendered
        $response->assertSee('HTTP 500', false);
    }

    public function test_production_404_response_does_not_leak_server_paths_or_framework_internals(): void
    {
        $response = $this->get('/_test_security/non-existent-endpoint-' . md5((string) mt_rand()));

        $response->assertStatus(404);
        $response->assertDontSee('C:\\xampp\\htdocs\\EASYPOS', false);
        $response->assertDontSee('vendor/laravel', false);
        $response->assertDontSee('NotFoundHttpException', false);
        $response->assertSee('HTTP 404', false);
    }

    public function test_production_403_response_renders_clean_forbidden_notice(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $cashier = User::where('emp_id', 'B01-CSH-001')->first();
        if (! $cashier) {
            $cashier = User::create([
                'name' => 'Cashier Sec07',
                'email' => 'cashier_sec07@easypos.test',
                'password' => Hash::make('password'),
                'emp_id' => 'B01-CSH-S07',
                'is_active' => true,
            ]);
            $cashier->assignRole('cashier');
        }

        // Cashier attempting to access /kitchen (forbidden)
        $response = $this->actingAs($cashier)->get('/kitchen');

        $response->assertStatus(403);
        $response->assertDontSee('C:\\xampp\\htdocs\\EASYPOS', false);
        $response->assertDontSee('AccessDeniedHttpException', false);
        $response->assertDontSee('AuthorizationException', false);
    }
}
