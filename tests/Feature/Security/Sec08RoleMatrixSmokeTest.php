<?php

namespace Tests\Feature\Security;

use App\Domains\Branches\Models\Branch;
use App\Domains\Identity\Models\User;
use App\Support\Enums\StaffRole;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class Sec08RoleMatrixSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branch;
    protected User $owner;
    protected User $superAdmin;
    protected User $branchManager;
    protected User $cashier;
    protected User $cook;
    protected User $chef;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->branch = Branch::firstOrCreate(['code' => 'B01'], [
            'name' => 'Matrix Branch',
            'is_active' => true,
        ]);

        $this->owner = User::firstOrCreate(['email' => 'owner@easypos.com'], [
            'name' => 'System Owner',
            'password' => Hash::make('password'),
            'emp_id' => 'HQ-ADM-001',
            'is_active' => true,
        ]);
        if (! $this->owner->hasRole(StaffRole::OWNER->value)) {
            $this->owner->assignRole(StaffRole::OWNER->value);
        }

        $this->superAdmin = User::create([
            'name' => 'Super Admin Sec08',
            'email' => 'superadmin_sec08@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'HQ-ADM-S08',
            'is_active' => true,
        ]);
        $this->superAdmin->assignRole(StaffRole::SUPER_ADMIN->value);

        $this->branchManager = User::create([
            'name' => 'Branch Manager Sec08',
            'email' => 'manager_sec08@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'B01-MGR-S08',
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);
        $this->branchManager->assignRole(StaffRole::BRANCH_MANAGER->value);

        $this->cashier = User::create([
            'name' => 'Cashier Sec08',
            'email' => 'cashier_sec08@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'B01-CSH-S08',
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);
        $this->cashier->assignRole(StaffRole::CASHIER->value);

        $this->cook = User::create([
            'name' => 'Cook Sec08',
            'email' => 'cook_sec08@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'B01-COK-S08',
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);
        $this->cook->assignRole(StaffRole::COOK->value);

        $this->chef = User::create([
            'name' => 'Chef Sec08',
            'email' => 'chef_sec08@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'B01-CHF-S08',
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);
        $this->chef->assignRole(StaffRole::CHEF->value);
    }

    public function test_guest_is_redirected_to_login_on_all_protected_endpoints(): void
    {
        $this->get('/pos')->assertRedirect('/login');
        $this->get('/kitchen')->assertRedirect('/login');
        $this->get('/menu')->assertRedirect('/login');
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_cashier_role_matrix(): void
    {
        $this->actingAs($this->cashier);

        // POS: Allowed
        $this->get('/pos')->assertStatus(200);

        // Kitchen: Denied
        $this->get('/kitchen')->assertStatus(403);

        // Menu: Denied
        $this->get('/menu')->assertStatus(403);

        // Admin panel: Denied
        $this->get('/admin')->assertStatus(403);
    }

    public function test_cook_role_matrix(): void
    {
        $this->actingAs($this->cook);

        // POS: Denied
        $this->get('/pos')->assertStatus(403);

        // Kitchen: Allowed
        $this->get('/kitchen')->assertStatus(200);

        // Menu: Denied
        $this->get('/menu')->assertStatus(403);

        // Admin panel: Denied
        $this->get('/admin')->assertStatus(403);
    }

    public function test_chef_role_matrix(): void
    {
        $this->actingAs($this->chef);

        // POS: Denied
        $this->get('/pos')->assertStatus(403);

        // Kitchen: Allowed
        $this->get('/kitchen')->assertStatus(200);

        // Menu: Allowed
        $this->get('/menu')->assertStatus(200);

        // Admin panel: Denied
        $this->get('/admin')->assertStatus(403);
    }

    public function test_branch_manager_role_matrix(): void
    {
        $this->actingAs($this->branchManager);

        // Admin panel: Allowed
        $this->get('/admin')->assertStatus(200);

        // Menu: Allowed
        $this->get('/menu')->assertStatus(200);

        // Back-office manager can access /pos
        $this->get('/pos')->assertStatus(200);
    }

    public function test_super_admin_role_matrix(): void
    {
        $this->actingAs($this->superAdmin);

        // Admin panel: Allowed
        $this->get('/admin')->assertStatus(200);

        // Super Admin represents HQ back office: floor stations are denied
        $this->get('/pos')->assertStatus(403);
        $this->get('/kitchen')->assertStatus(403);
        $this->get('/menu')->assertStatus(403);
    }

    public function test_owner_role_matrix(): void
    {
        $this->actingAs($this->owner);

        // Owner has admin, pos register, and menu view access
        $this->get('/admin')->assertStatus(200);
        $this->get('/pos')->assertStatus(200);
        $this->get('/menu')->assertStatus(200);

        // Floor kitchen display is restricted to kitchen staff
        $this->get('/kitchen')->assertStatus(403);
    }
}
