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

    public function test_filament_login_page_renders_with_link_to_floor_staff_terminal(): void
    {
        $response = $this->get('/admin/login');

        $response->assertStatus(200);
        $response->assertSee('Floor Staff Terminal');
    }

    public function test_cashier_cannot_access_filament_admin_panel(): void
    {
        $cashier = User::where('emp_id', 'B01-CSH-001')->first();

        $response = $this->actingAs($cashier)->get('/admin');

        $response->assertStatus(403);
    }

    public function test_owner_can_access_filament_admin_panel(): void
    {
        $owner = User::where('email', 'owner@easypos.com')->first();

        $response = $this->actingAs($owner)->get('/admin');

        $response->assertStatus(200);
    }

    public function test_branch_manager_can_access_filament_admin_panel(): void
    {
        $manager = User::where('email', 'mgr-b01@easypos.com')->first();

        $response = $this->actingAs($manager)->get('/admin');

        $response->assertStatus(200);
    }

    public function test_branch_manager_with_pin_terminal_session_is_forbidden_from_admin_panel(): void
    {
        $manager = User::where('email', 'mgr-b01@easypos.com')->first();

        // Simulate session logged in via floor terminal PIN
        $response = $this->actingAs($manager)
            ->withSession(['auth_method' => 'pin'])
            ->get('/admin');

        $response->assertStatus(403);
    }
}
