<?php

namespace Tests\Feature;

use App\Domains\Identity\Livewire\Auth\Login;
use App\Domains\Identity\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_floor_staff_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertSee('Floor Staff Terminal');
        $response->assertSee('Employee ID');
    }

    public function test_cashier_can_authenticate_using_emp_id_and_pin(): void
    {
        Livewire::test(Login::class)
            ->set('emp_id', 'B01-CSH-001')
            ->set('pin', '1234')
            ->call('login')
            ->assertHasNoErrors()
            ->assertRedirect(route('pos'));

        $this->assertAuthenticated();
        $this->assertEquals('B01-CSH-001', auth()->user()->emp_id);
    }

    public function test_chef_can_authenticate_using_emp_id_and_pin(): void
    {
        Livewire::test(Login::class)
            ->set('emp_id', 'B01-CHF-001')
            ->set('pin', '2345')
            ->call('login')
            ->assertHasNoErrors()
            ->assertRedirect(route('inventory'));

        $this->assertAuthenticated();
        $this->assertEquals('B01-CHF-001', auth()->user()->emp_id);
    }

    public function test_cook_can_authenticate_using_emp_id_and_pin(): void
    {
        Livewire::test(Login::class)
            ->set('emp_id', 'B01-COK-001')
            ->set('pin', '3456')
            ->call('login')
            ->assertHasNoErrors()
            ->assertRedirect('/kitchen');

        $this->assertAuthenticated();
        $this->assertEquals('B01-COK-001', auth()->user()->emp_id);
    }

    public function test_branch_manager_can_authenticate_using_emp_id_and_pin_and_is_directed_to_pos(): void
    {
        Livewire::test(Login::class)
            ->set('emp_id', 'B01-MGR-001')
            ->set('pin', '9999')
            ->call('login')
            ->assertHasNoErrors()
            ->assertRedirect(route('pos'));

        $this->assertAuthenticated();
        $this->assertEquals('B01-MGR-001', auth()->user()->emp_id);
        $this->assertEquals('pin', session('auth_method'));
    }

    public function test_staff_can_input_pin_using_keypad_actions(): void
    {
        Livewire::test(Login::class)
            ->set('emp_id', 'B01-CSH-001')
            ->call('appendDigit', '1')
            ->call('appendDigit', '2')
            ->call('appendDigit', '9')
            ->call('backspace')
            ->call('appendDigit', '3')
            ->call('appendDigit', '4') // Should trigger auto-login on 4th digit
            ->assertHasNoErrors()
            ->assertRedirect(route('pos'));

        $this->assertAuthenticated();
    }

    public function test_staff_cannot_authenticate_with_invalid_pin(): void
    {
        Livewire::test(Login::class)
            ->set('emp_id', 'B01-CSH-001')
            ->set('pin', '9999')
            ->call('login')
            ->assertHasErrors(['emp_id']);

        $this->assertGuest();
    }

    public function test_staff_cannot_authenticate_with_nonexistent_emp_id(): void
    {
        Livewire::test(Login::class)
            ->set('emp_id', 'NON-EXISTENT')
            ->set('pin', '1234')
            ->call('login')
            ->assertHasErrors(['emp_id']);

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::where('emp_id', 'B01-CSH-001')->first();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }

    public function test_theme_cookie_renders_dark_mode_on_login_screen(): void
    {
        $response = $this->withCookie('theme', 'dark')->get('/login');

        $response->assertStatus(200);
        $response->assertSee('<html lang="en" class="h-full dark">', false);
    }

    public function test_theme_cookie_renders_dark_mode_on_inventory_screen_for_chef(): void
    {
        $chef = User::where('emp_id', 'B01-CHF-001')->first();

        $response = $this->actingAs($chef)
            ->withCookie('theme', 'dark')
            ->get('/inventory');

        $response->assertStatus(200);
        $response->assertSee('<html lang="en" class="dark">', false);
        $response->assertSee('Inventory Stock Alerts');
        $response->assertSee('Stock Catalog');
    }
}
