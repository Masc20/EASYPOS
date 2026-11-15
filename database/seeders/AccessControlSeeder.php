<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AccessControlSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach ([
            'admin.access', 'pos.access', 'pos.checkout', 'pos.refund',
            'kitchen.view', 'kitchen.prepare', 'menu.view', 'menu.manage',
            'branch.manage', 'users.manage', 'reports.view', 'financial.control',
        ] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $roles = [
            'owner' => ['admin.access', 'pos.access', 'pos.checkout', 'pos.refund', 'menu.view', 'menu.manage', 'branch.manage', 'users.manage', 'reports.view', 'financial.control'],
            'super-admin' => ['admin.access', 'pos.refund', 'menu.manage', 'branch.manage', 'users.manage', 'reports.view', 'financial.control'],
            'branch-manager' => ['admin.access', 'pos.access', 'pos.checkout', 'pos.refund', 'kitchen.view', 'kitchen.prepare', 'menu.view', 'menu.manage', 'users.manage', 'reports.view'],
            'cashier' => ['pos.access', 'pos.checkout'],
            'cook' => ['kitchen.view', 'kitchen.prepare'],
            'chef' => ['kitchen.view', 'kitchen.prepare', 'menu.view', 'menu.manage'],
        ];

        foreach ($roles as $name => $permissions) {
            Role::firstOrCreate(['name' => $name, 'guard_name' => 'web'])
                ->syncPermissions($permissions);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
