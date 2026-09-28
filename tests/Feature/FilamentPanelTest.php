<?php

namespace Tests\Feature;

use App\Domains\Identity\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FilamentPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_guests_are_redirected_to_filament_login(): void
    {
        $response = $this->get('/admin');

        $response->assertRedirect('/admin/login');
    }

    public function test_cashier_cannot_access_filament_admin_panel(): void
    {
        $cashier = User::where('email', 'cashier@easypos.com')->first();

        $response = $this->actingAs($cashier)->get('/admin');

        $response->assertStatus(403);
    }

    public function test_admin_can_access_filament_admin_panel(): void
    {
        $admin = User::where('email', 'admin@easypos.com')->first();

        $response = $this->actingAs($admin)->get('/admin');

        $response->assertStatus(200);
    }
}
