<?php

namespace Tests\Feature;

use App\Domains\Identity\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_guests_are_redirected_to_login_when_accessing_pos(): void
    {
        $response = $this->get('/pos');

        $response->assertRedirect('/login');
    }

    public function test_guests_are_redirected_to_login_when_accessing_inventory(): void
    {
        $response = $this->get('/inventory');

        $response->assertRedirect('/login');
    }

    public function test_cashier_can_access_pos(): void
    {
        $cashier = User::where('email', 'cashier@easypos.com')->first();

        $response = $this->actingAs($cashier)->get('/pos');

        $response->assertStatus(200);
    }

    public function test_cashier_without_inventory_permission_is_forbidden_from_inventory(): void
    {
        $cashier = User::where('email', 'cashier@easypos.com')->first();

        $response = $this->actingAs($cashier)->get('/inventory');

        $response->assertStatus(403);
    }

    public function test_admin_can_access_both_pos_and_inventory(): void
    {
        $admin = User::where('email', 'admin@easypos.com')->first();

        $posResponse = $this->actingAs($admin)->get('/pos');
        $posResponse->assertStatus(200);

        $inventoryResponse = $this->actingAs($admin)->get('/inventory');
        $inventoryResponse->assertStatus(200);
    }
}
