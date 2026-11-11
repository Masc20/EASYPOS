<?php

namespace Tests\Feature\Security;

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class Sec09PermissionCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_roles_and_permissions_seeder_is_strictly_idempotent(): void
    {
        // 1. Initial seeding
        $this->seed(RolesAndPermissionsSeeder::class);

        $initialRoleCount = Role::count();
        $initialPermissionCount = Permission::count();

        $ownerPermissionsPass1 = Role::findByName('owner')->permissions->pluck('name')->sort()->values()->toArray();
        $superAdminPermissionsPass1 = Role::findByName('super-admin')->permissions->pluck('name')->sort()->values()->toArray();
        $managerPermissionsPass1 = Role::findByName('branch-manager')->permissions->pluck('name')->sort()->values()->toArray();

        // 2. Second seeding run (simulating deployment / reseeding)
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertEquals($initialRoleCount, Role::count(), 'Reseeding must not duplicate roles.');
        $this->assertEquals($initialPermissionCount, Permission::count(), 'Reseeding must not duplicate permissions.');

        $ownerPermissionsPass2 = Role::findByName('owner')->permissions->pluck('name')->sort()->values()->toArray();
        $superAdminPermissionsPass2 = Role::findByName('super-admin')->permissions->pluck('name')->sort()->values()->toArray();
        $managerPermissionsPass2 = Role::findByName('branch-manager')->permissions->pluck('name')->sort()->values()->toArray();

        $this->assertEquals($ownerPermissionsPass1, $ownerPermissionsPass2, 'Owner role grants must remain identical.');
        $this->assertEquals($superAdminPermissionsPass1, $superAdminPermissionsPass2, 'Super Admin role grants must remain identical.');
        $this->assertEquals($managerPermissionsPass1, $managerPermissionsPass2, 'Branch Manager role grants must remain identical.');
    }

    public function test_stale_or_custom_permissions_are_not_automatically_granted_to_global_roles(): void
    {
        // Insert a synthetic stale permission before seeding
        Permission::create(['name' => 'legacy.unauthorized_access', 'guard_name' => 'web']);

        $this->seed(RolesAndPermissionsSeeder::class);

        $ownerRole = Role::findByName('owner');
        $superAdminRole = Role::findByName('super-admin');
        $managerRole = Role::findByName('branch-manager');

        // Global roles must NOT receive stale permissions via wildcard sync
        $this->assertFalse(
            $ownerRole->hasPermissionTo('legacy.unauthorized_access'),
            'Owner role must not inherit non-canonical permissions.'
        );
        $this->assertFalse(
            $superAdminRole->hasPermissionTo('legacy.unauthorized_access'),
            'Super Admin role must not inherit non-canonical permissions.'
        );
        $this->assertFalse(
            $managerRole->hasPermissionTo('legacy.unauthorized_access'),
            'Branch Manager must not inherit non-canonical permissions.'
        );
    }
}
