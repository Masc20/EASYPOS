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

    public function test_guests_are_redirected_to_login_when_accessing_menu(): void
    {
        $response = $this->get('/menu');

        $response->assertRedirect('/login');
    }

    public function test_cashier_can_access_pos(): void
    {
        $cashier = User::where('emp_id', 'B01-CSH-001')->first();

        $response = $this->actingAs($cashier)->get('/pos');

        $response->assertStatus(200);
    }

    public function test_cashier_without_menu_permission_is_forbidden_from_menu(): void
    {
        $cashier = User::where('emp_id', 'B01-CSH-001')->first();

        $response = $this->actingAs($cashier)->get('/menu');

        $response->assertStatus(403);
    }

    public function test_chef_can_access_menu(): void
    {
        $chef = User::where('emp_id', 'B01-CHF-001')->first();

        $response = $this->actingAs($chef)->get('/menu');

        $response->assertStatus(200);
    }

    public function test_owner_can_access_both_pos_and_menu(): void
    {
        $owner = User::where('email', 'owner@easypos.com')->first();

        $posResponse = $this->actingAs($owner)->get('/pos');
        $posResponse->assertStatus(200);

        $menuResponse = $this->actingAs($owner)->get('/menu');
        $menuResponse->assertStatus(200);
    }

    public function test_cook_can_access_kitchen(): void
    {
        $cook = User::where('emp_id', 'B01-COK-001')->first();

        $response = $this->actingAs($cook)->get('/kitchen');

        $response->assertStatus(200);
        $response->assertSee('Kitchen Display System (KDS)');
    }

    public function test_cook_without_pos_permission_is_forbidden_from_pos(): void
    {
        $cook = User::where('emp_id', 'B01-COK-001')->first();

        $response = $this->actingAs($cook)->get('/pos');

        $response->assertStatus(403);
    }

    public function test_cook_without_menu_permission_is_forbidden_from_menu(): void
    {
        $cook = User::where('emp_id', 'B01-COK-001')->first();

        $response = $this->actingAs($cook)->get('/menu');

        $response->assertStatus(403);
    }

    public function test_cashier_is_forbidden_from_kitchen(): void
    {
        $cashier = User::where('emp_id', 'B01-CSH-001')->first();

        $response = $this->actingAs($cashier)->get('/kitchen');

        $response->assertStatus(403);
    }

    public function test_chef_without_pos_permission_is_forbidden_from_pos(): void
    {
        $chef = User::where('emp_id', 'B01-CHF-001')->first();

        $response = $this->actingAs($chef)->get('/pos');

        $response->assertStatus(403);
    }

    public function test_cashier_visiting_root_is_redirected_to_pos(): void
    {
        $cashier = User::where('emp_id', 'B01-CSH-001')->first();

        $response = $this->actingAs($cashier)->get('/');

        $response->assertRedirect(route('pos'));
    }

    public function test_chef_visiting_root_is_redirected_to_menu(): void
    {
        $chef = User::where('emp_id', 'B01-CHF-001')->first();

        $response = $this->actingAs($chef)->get('/');

        $response->assertRedirect(route('menu'));
    }

    public function test_cook_visiting_root_is_redirected_to_kitchen(): void
    {
        $cook = User::where('emp_id', 'B01-COK-001')->first();

        $response = $this->actingAs($cook)->get('/');

        $response->assertRedirect(route('kitchen'));
    }

    public function test_floor_staff_do_not_see_unauthorized_nav_links(): void
    {
        $cashier = User::where('emp_id', 'B01-CSH-001')->first();

        $response = $this->actingAs($cashier)->get('/pos');

        $response->assertStatus(200);
        $response->assertSee('POS Register Terminal');
        $response->assertDontSee('href="' . route('menu') . '"', false);
        $response->assertDontSee('href="' . route('kitchen') . '"', false);
        $response->assertDontSee('Admin Back-Office');
    }

    public function test_chef_can_access_kitchen_and_prepare_orders(): void
    {
        $chef = User::where('emp_id', 'B01-CHF-001')->first();

        $this->assertTrue($chef->can('kitchen.view'));
        $this->assertTrue($chef->can('kitchen.prepare'));
        $this->assertTrue($chef->can('menu.manage'));

        $response = $this->actingAs($chef)->get('/kitchen');
        $response->assertStatus(200);
    }

    public function test_chef_sees_both_menu_and_kitchen_nav_links(): void
    {
        $chef = User::where('emp_id', 'B01-CHF-001')->first();

        $response = $this->actingAs($chef)->get('/menu');

        $response->assertStatus(200);
        $response->assertSee('Menu Catalog');
        $response->assertSee('Kitchen Display');
        $response->assertDontSee('Point of Sale');
        $response->assertDontSee('Admin Back-Office');
    }
}
