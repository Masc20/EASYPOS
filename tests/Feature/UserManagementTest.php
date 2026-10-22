<?php

namespace Tests\Feature;

use App\Domains\Identity\Actions\ToggleUserActiveStatusAction;
use App\Domains\Identity\Livewire\Auth\Login;
use App\Domains\Identity\Models\User;
use App\Filament\Resources\Users\Pages\ListUsers;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Panel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
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

    public function test_admin_cannot_deactivate_their_own_account(): void
    {
        $action = app(ToggleUserActiveStatusAction::class);

        $this->expectException(ValidationException::class);
        $action->execute($this->admin, $this->admin);
    }

    public function test_admin_can_deactivate_staff_account_and_revoke_sessions(): void
    {
        $action = app(ToggleUserActiveStatusAction::class);

        // Seed a dummy session for the cashier
        DB::table('sessions')->insert([
            'id' => 'dummy_session_123',
            'user_id' => $this->cashier->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'payload' => 'payload_data',
            'last_activity' => time(),
        ]);

        $this->assertDatabaseHas('sessions', ['user_id' => $this->cashier->id]);

        $updated = $action->execute($this->cashier, $this->admin);

        $this->assertFalse((bool) $updated->is_active);
        $this->assertTrue($updated->isDeactivated());
        $this->assertDatabaseHas('users', [
            'id' => $this->cashier->id,
            'is_active' => false,
        ]);

        // Assert session was revoked
        $this->assertDatabaseMissing('sessions', ['user_id' => $this->cashier->id]);
    }

    public function test_deactivated_floor_staff_cannot_login_with_pin(): void
    {
        // Deactivate cashier
        $this->cashier->update(['is_active' => false]);

        Livewire::test(Login::class)
            ->set('emp_id', $this->cashier->emp_id)
            ->set('pin', '1234')
            ->call('login')
            ->assertHasErrors(['emp_id']);

        $this->assertGuest();
    }

    public function test_deactivated_user_cannot_access_filament_panel(): void
    {
        $panel = new Panel();
        $panel->id('admin');

        $this->cashier->update(['is_active' => false]);
        $this->assertFalse($this->cashier->canAccessPanel($panel));

        $this->admin->update(['is_active' => false]);
        $this->assertFalse($this->admin->canAccessPanel($panel));
    }

    public function test_admin_can_reactivate_staff_account(): void
    {
        $this->cashier->update(['is_active' => false]);
        $this->assertFalse($this->cashier->fresh()->isActive());

        $action = app(ToggleUserActiveStatusAction::class);
        $reactivated = $action->execute($this->cashier->fresh(), $this->admin);

        $this->assertTrue((bool) $reactivated->is_active);
        $this->assertTrue($reactivated->isActive());
        $this->assertDatabaseHas('users', [
            'id' => $this->cashier->id,
            'is_active' => true,
        ]);

        // Reactivated staff can log in with PIN again
        Livewire::test(Login::class)
            ->set('emp_id', $this->cashier->emp_id)
            ->set('pin', '1234')
            ->call('login')
            ->assertHasNoErrors()
            ->assertRedirect(route('pos'));

        $this->assertAuthenticatedAs($this->cashier);
    }

    public function test_filament_users_table_renders_status_badge(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/users');

        $response->assertStatus(200);
        $response->assertSee('Status');
        $response->assertSee('Active');
    }

    public function test_admin_can_deactivate_and_reactivate_via_filament_table_actions(): void
    {
        Livewire::actingAs($this->admin)
            ->test(ListUsers::class)
            ->callTableAction('deactivate', $this->cashier);

        $this->assertFalse((bool) $this->cashier->fresh()->is_active);

        Livewire::actingAs($this->admin)
            ->test(ListUsers::class)
            ->callTableAction('reactivate', $this->cashier);

        $this->assertTrue((bool) $this->cashier->fresh()->is_active);
    }
}
