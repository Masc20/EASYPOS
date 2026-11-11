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
            'pos.access' => 'Open POS Register',
            'pos.checkout' => 'Ring Up Orders & Collect Payment',
            'pos.refund' => 'Approve Voids & Refunds',
            'pos.discount' => 'Apply Manual Discounts',
            'menu.view' => 'View Menu Floor Catalog',
            'menu.manage' => 'Manage Dishes, Prices & Availability',
            'kitchen.view' => 'Open Kitchen Display (KDS)',
            'kitchen.prepare' => 'Claim & Advance Kitchen Tickets',
            'kitchen.manage' => 'Manage Kitchen Stations',
            'admin.access' => 'Open Back-Office Portal',
            'branch.manage', 'branches.manage' => 'Manage Branches & Store Locations',
            'roles.manage' => 'Manage Roles & Permissions',
            'users.manage' => 'Manage Staff Accounts',
            'reports.view' => 'View Sales Reports & Exports',
            'financial.control' => 'Financial & Tax Controls',
            default => str_replace(['.', '-', '_'], ' ', ucwords($permission, '.-_')),
        };
    }

    /**
     * Plain-language explanation of what each permission allows.
     */
    public static function description(string $permission): string
    {
        return match ($permission) {
            'pos.access' => 'Open the /pos register screen and browse branch menu items.',
            'pos.checkout' => 'Create orders, accept cash/card/QR payments, and print receipts.',
            'pos.refund' => 'Approve order voids and refunds using a manager PIN or back-office action.',
            'pos.discount' => 'Authorize custom manual discounts during checkout.',
            'menu.view' => 'Open the /menu floor catalog and inspect dishes and stock counts.',
            'menu.manage' => 'Create, edit, archive, and 86/restore dishes within authorized branch scope.',
            'kitchen.view' => 'Open the /kitchen display queue and view active preparation tickets.',
            'kitchen.prepare' => 'Claim tickets, mark items cooked, mark orders ready/served, and recall tickets.',
            'admin.access' => 'Sign in to the /admin back-office portal using email and password.',
            'branch.manage', 'branches.manage' => 'Create and edit store branches (HQ System Admin / Owner only).',
            'users.manage' => 'Create, edit, and deactivate staff accounts within authorized branch scope.',
            'reports.view' => 'View sales dashboards, transaction history, and printable/CSV audit reports.',
            'financial.control' => 'Access HQ-wide financial, tax, and executive reconciliation controls.',
            default => 'System capability permission.',
        };
    }

    /**
     * Scope badge label for each permission.
     */
    public static function scopeLabel(string $permission): string
    {
        return match ($permission) {
            'branch.manage', 'branches.manage', 'roles.manage', 'financial.control' => 'HQ / Global Only',
            'admin.access', 'users.manage', 'reports.view', 'menu.manage' => 'Branch or Global by Role',
            default => 'Assigned Branch Station',
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
