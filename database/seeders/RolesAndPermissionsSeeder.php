<?php

namespace Database\Seeders;

use App\Domains\Identity\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Define permissions
        $permissions = [
            'admin.access',
            'pos.access',
            'pos.checkout',
            'pos.refund',
            'inventory.view',
            'inventory.manage',
            'users.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Define roles and assign permissions
        $superAdminRole = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        $superAdminRole->syncPermissions(Permission::all());

        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $adminRole->syncPermissions(Permission::all());

        $managerRole = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        $managerRole->syncPermissions([
            'admin.access',
            'pos.access',
            'pos.checkout',
            'pos.refund',
            'inventory.view',
            'inventory.manage',
        ]);

        $cashierRole = Role::firstOrCreate(['name' => 'cashier', 'guard_name' => 'web']);
        $cashierRole->syncPermissions([
            'pos.access',
            'pos.checkout',
        ]);

        $inventoryClerkRole = Role::firstOrCreate(['name' => 'inventory-clerk', 'guard_name' => 'web']);
        $inventoryClerkRole->syncPermissions([
            'inventory.view',
            'inventory.manage',
        ]);

        // Seed default users for development & testing
        $admin = User::firstOrCreate(
            ['email' => 'admin@easypos.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        $admin->syncRoles([$superAdminRole]);

        $manager = User::firstOrCreate(
            ['email' => 'manager@easypos.com'],
            [
                'name' => 'Store Manager',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        $manager->syncRoles([$managerRole]);

        $cashier = User::firstOrCreate(
            ['email' => 'cashier@easypos.com'],
            [
                'name' => 'Cashier One',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        $cashier->syncRoles([$cashierRole]);
    }
}
