<?php

namespace Tests\Feature\Security;

use App\Domains\Branches\Models\Branch;
use App\Domains\Identity\Models\User;
use App\Filament\Resources\Branches\BranchResource;
use App\Filament\Resources\Roles\RoleResource;
use App\Support\Enums\StaffRole;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class Sec06HqResourceRestrictionTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branch;
    protected User $owner;
    protected User $superAdmin;
    protected User $branchManager;
    protected User $cashier;
    protected User $chef;
    protected User $cook;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->branch = Branch::firstOrCreate(['code' => 'B01'], [
            'name' => 'HQ Restriction Branch',
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
            'name' => 'Super Administrator',
            'email' => 'superadmin_sec06@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'HQ-ADM-S06',
            'is_active' => true,
        ]);
        $this->superAdmin->assignRole(StaffRole::SUPER_ADMIN->value);

        $this->branchManager = User::create([
            'name' => 'Branch Manager Sec06',
            'email' => 'manager_sec06@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'B01-MGR-S06',
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);
        $this->branchManager->assignRole(StaffRole::BRANCH_MANAGER->value);

        $this->cashier = User::create([
            'name' => 'Cashier Sec06',
            'email' => 'cashier_sec06@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'B01-CSH-S06',
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);
        $this->cashier->assignRole(StaffRole::CASHIER->value);

        $this->chef = User::create([
            'name' => 'Chef Sec06',
            'email' => 'chef_sec06@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'B01-CHF-S06',
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);
        $this->chef->assignRole(StaffRole::CHEF->value);

        $this->cook = User::create([
            'name' => 'Cook Sec06',
            'email' => 'cook_sec06@easypos.test',
            'password' => Hash::make('password'),
            'emp_id' => 'B01-COK-S06',
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);
        $this->cook->assignRole(StaffRole::COOK->value);
    }

    public function test_owner_and_super_admin_have_full_access_to_hq_resources(): void
    {
        $this->actingAs($this->owner);
        $this->assertTrue(BranchResource::canAccess());
        $this->assertTrue(BranchResource::shouldRegisterNavigation());
        $this->assertTrue(RoleResource::canAccess());
        $this->assertTrue(RoleResource::shouldRegisterNavigation());

        $this->actingAs($this->superAdmin);
        $this->assertTrue(BranchResource::canAccess());
        $this->assertTrue(BranchResource::shouldRegisterNavigation());
        $this->assertTrue(RoleResource::canAccess());
        $this->assertTrue(RoleResource::shouldRegisterNavigation());
    }

    public function test_branch_manager_is_denied_access_to_branch_resource(): void
    {
        $this->actingAs($this->branchManager);

        $this->assertFalse(BranchResource::canAccess());
        $this->assertFalse(BranchResource::shouldRegisterNavigation());

        $response = $this->get('/admin/branches');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function test_branch_manager_is_denied_access_to_role_resource(): void
    {
        $this->actingAs($this->branchManager);

        $this->assertFalse(RoleResource::canAccess());
        $this->assertFalse(RoleResource::shouldRegisterNavigation());

        $response = $this->get('/admin/roles');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function test_floor_staff_are_denied_access_to_hq_resources(): void
    {
        foreach ([$this->cashier, $this->chef, $this->cook] as $staff) {
            $this->actingAs($staff);

            $this->assertFalse(BranchResource::canAccess(), "{$staff->name} should not access BranchResource");
            $this->assertFalse(BranchResource::shouldRegisterNavigation());
            $this->assertFalse(RoleResource::canAccess(), "{$staff->name} should not access RoleResource");
            $this->assertFalse(RoleResource::shouldRegisterNavigation());

            $branchResponse = $this->get('/admin/branches');
            $this->assertEquals(403, $branchResponse->getStatusCode());

            $roleResponse = $this->get('/admin/roles');
            $this->assertEquals(403, $roleResponse->getStatusCode());
        }
    }

    public function test_unauthenticated_guest_is_redirected_to_login(): void
    {
        $this->assertFalse(BranchResource::canAccess());
        $this->assertFalse(RoleResource::canAccess());

        $branchResponse = $this->get('/admin/branches');
        $branchResponse->assertRedirect('/admin/login');

        $roleResponse = $this->get('/admin/roles');
        $roleResponse->assertRedirect('/admin/login');
    }
}
