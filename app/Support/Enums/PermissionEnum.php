<?php

namespace App\Support\Enums;

class PermissionEnum
{
    /**
     * Map raw database permission names to human-readable descriptive words.
     */
    public static function label(string $permission): string
    {
        return match ($permission) {
            'pos.access' => 'Point of Sale Terminal Access',
            'pos.refund' => 'Approve Voids & Refunds',
            'pos.discount' => 'Apply Manual Discounts',
            'menu.view' => 'View Chef Menu Catalog',
            'menu.manage' => 'Manage Dishes & Pricing',
            'kitchen.view' => 'Kitchen Display System (KDS)',
            'kitchen.manage' => 'Manage Kitchen Stations',
            'admin.access' => 'Access Back-Office Portal',
            'branches.manage' => 'Manage Branches & Stores',
            'roles.manage' => 'Manage Roles & Permissions',
            'users.manage' => 'Manage Staff & User Accounts',
            'reports.view' => 'View Sales Records & Reports',
            default => str_replace(['.', '-', '_'], ' ', ucwords($permission, '.-_')),
        };
    }

    /**
     * Get grouped list of permissions for forms.
     */
    public static function allWithLabels(): array
    {
        $permissions = \Spatie\Permission\Models\Permission::pluck('name', 'name')->toArray();
        $mapped = [];

        foreach ($permissions as $perm) {
            $mapped[$perm] = self::label($perm);
        }

        return $mapped;
    }
}
