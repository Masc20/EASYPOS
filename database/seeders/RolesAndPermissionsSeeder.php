<?php

namespace Database\Seeders;

use App\Domains\Branches\Models\Branch;
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

        // 1. Define permissions
        $permissions = [
            'admin.access',
            'pos.access',
            'pos.checkout',
            'pos.refund',
            'kitchen.view',
            'kitchen.prepare',
            'inventory.view',
            'inventory.manage',
            'branch.manage',
            'users.manage',
            'reports.view',
            'financial.control',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // 2. Define roles and assign permissions
        $ownerRole = Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        $ownerRole->syncPermissions(Permission::all());

        $superAdminRole = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        $superAdminRole->syncPermissions(Permission::all());

        $branchManagerRole = Role::firstOrCreate(['name' => 'branch-manager', 'guard_name' => 'web']);
        $branchManagerRole->syncPermissions([
            'admin.access',
            'pos.access',
            'pos.checkout',
            'pos.refund',
            'kitchen.view',
            'inventory.view',
            'inventory.manage',
            'branch.manage',
            'reports.view',
        ]);

        $cashierRole = Role::firstOrCreate(['name' => 'cashier', 'guard_name' => 'web']);
        $cashierRole->syncPermissions([
            'pos.access',
            'pos.checkout',
        ]);

        $cookRole = Role::firstOrCreate(['name' => 'cook', 'guard_name' => 'web']);
        $cookRole->syncPermissions([
            'kitchen.view',
            'kitchen.prepare',
        ]);

        $chefRole = Role::firstOrCreate(['name' => 'chef', 'guard_name' => 'web']);
        $chefRole->syncPermissions([
            'kitchen.view',
            'inventory.view',
            'inventory.manage',
        ]);

        // 3. Seed Branches
        $branchManila = Branch::firstOrCreate(
            ['code' => 'B01'],
            [
                'name' => 'Manila Flagship Branch',
                'address' => '123 Taft Avenue, Manila',
                'phone' => '+63 2 8123 4567',
                'is_active' => true,
            ]
        );

        $branchCebu = Branch::firstOrCreate(
            ['code' => 'B02'],
            [
                'name' => 'Cebu IT Park Branch',
                'address' => '456 Salinas Drive, Cebu City',
                'phone' => '+63 32 412 3456',
                'is_active' => true,
            ]
        );

        // 4. Seed Global Owner (Email + Password)
        $owner = User::firstOrCreate(
            ['email' => 'owner@easypos.com'],
            [
                'name' => 'System Owner',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        $owner->syncRoles([$ownerRole]);

        // 5. Seed Branch 1 Staff (EmpID + 4-digit PIN)
        $cashier1 = User::firstOrCreate(
            ['emp_id' => 'B01-CSH-001'],
            [
                'name' => 'Maria Santos (Cashier)',
                'branch_id' => $branchManila->id,
                'pin_code' => Hash::make('1234'),
            ]
        );
        $cashier1->syncRoles([$cashierRole]);

        $chef1 = User::firstOrCreate(
            ['emp_id' => 'B01-CHF-001'],
            [
                'name' => 'Chef Marco (Chef)',
                'branch_id' => $branchManila->id,
                'pin_code' => Hash::make('2345'),
            ]
        );
        $chef1->syncRoles([$chefRole]);

        $cook1 = User::firstOrCreate(
            ['emp_id' => 'B01-COK-001'],
            [
                'name' => 'Cook Gordon (Cook)',
                'branch_id' => $branchManila->id,
                'pin_code' => Hash::make('3456'),
            ]
        );
        $cook1->syncRoles([$cookRole]);

        $mgr1 = User::firstOrCreate(
            ['email' => 'mgr-b01@easypos.com'],
            [
                'emp_id' => 'B01-MGR-001',
                'name' => 'Carlos Reyes (Branch Manager)',
                'branch_id' => $branchManila->id,
                'password' => Hash::make('password'),
                'pin_code' => Hash::make('9999'),
                'email_verified_at' => now(),
            ]
        );
        $mgr1->syncRoles([$branchManagerRole]);

        // 6. Seed Branch 2 Staff (EmpID + 4-digit PIN)
        $cashier2 = User::firstOrCreate(
            ['emp_id' => 'B02-CSH-001'],
            [
                'name' => 'Juan Dela Cruz (Cashier)',
                'branch_id' => $branchCebu->id,
                'pin_code' => Hash::make('1234'),
            ]
        );
        $cashier2->syncRoles([$cashierRole]);

        $chef2 = User::firstOrCreate(
            ['emp_id' => 'B02-CHF-001'],
            [
                'name' => 'Chef Antonio (Chef)',
                'branch_id' => $branchCebu->id,
                'pin_code' => Hash::make('2345'),
            ]
        );
        $chef2->syncRoles([$chefRole]);

        $cook2 = User::firstOrCreate(
            ['emp_id' => 'B02-COK-001'],
            [
                'name' => 'Cook Roberto (Cook)',
                'branch_id' => $branchCebu->id,
                'pin_code' => Hash::make('3456'),
            ]
        );
        $cook2->syncRoles([$cookRole]);
    }
}
