<?php

namespace Tests\Feature;

use App\Domains\Identity\Livewire\Auth\Login;
use App\Domains\Identity\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;
    protected User $admin;
    protected User $cashier;
    protected User $cook;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->owner = User::where('email', 'owner@easypos.com')->firstOrFail();
        $this->admin = User::where('email', 'admin@easypos.com')->firstOrFail();
        $this->cashier = User::where('emp_id', 'B01-CSH-001')->firstOrFail();
        $this->cook = User::where('emp_id', 'B01-COK-001')->firstOrFail();
    }

    public function test_admin_can_access_user_management_in_filament(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/users');

        $response->assertStatus(200);
        $response->assertSee('Staff & Users');
        $response->assertSee($this->cashier->name);
    }

    public function test_owner_can_access_user_management_in_filament(): void
    {
        $response = $this->actingAs($this->owner)->get('/admin/users');

        $response->assertStatus(200);
    }

    public function test_cashier_is_forbidden_from_user_management(): void
    {
        $response = $this->actingAs($this->cashier)->get('/admin/users');

        $response->assertStatus(403);
    }

    public function test_cook_is_forbidden_from_user_management(): void
    {
        $response = $this->actingAs($this->cook)->get('/admin/users');

        $response->assertStatus(403);
    }

    public function test_can_create_floor_staff_user_with_pin(): void
    {
        $staff = User::create([
            'name' => 'John Doe (Cashier)',
            'emp_id' => 'B01-CSH-099',
            'branch_id' => 1,
            'pin_code' => Hash::make('5555'),
        ]);

        $staff->assignRole('cashier');

        $this->assertDatabaseHas('users', [
            'emp_id' => 'B01-CSH-099',
        ]);

        $this->assertTrue($staff->hasRole('cashier'));
        $this->assertTrue($staff->verifyPin('5555'));
    }

    public function test_can_create_admin_user_with_password(): void
    {
        $manager = User::create([
            'name' => 'Jane Manager',
            'email' => 'jane@easypos.com',
            'password' => Hash::make('password123'),
        ]);

        $manager->assignRole('branch-manager');

        $this->assertDatabaseHas('users', [
            'email' => 'jane@easypos.com',
        ]);

        $this->assertTrue($manager->hasRole('branch-manager'));
        $this->assertTrue(Hash::check('password123', $manager->password));
    }
}
