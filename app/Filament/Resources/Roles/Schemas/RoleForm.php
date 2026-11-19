<?php

namespace App\Filament\Resources\Roles\Schemas;

use App\Support\Enums\PermissionEnum;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleForm
{
    private const PERMISSION_TABS = [
        'permissions_pos' => [
            'pos.access',
            'pos.checkout',
            'pos.discount',
            'pos.refund',
        ],
        'permissions_operations' => [
            'menu.view',
            'kitchen.view',
            'kitchen.status',
            'kitchen.prepare',
            'kitchen.manage',
        ],
        'permissions_management' => [
            'menu.manage',
            'reports.view',
            'users.manage',
            'financial.control',
            'admin.access',
            'branch.manage',
            'branches.manage',
            'roles.manage',
        ],
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Role Identifier')
                    ->description('Lower-kebab-case slug matching operational responsibilities.')
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name')
                            ->label('Role Slug')
                            ->required()
                            ->maxLength(255)
                            ->unique('roles', 'name', ignoreRecord: true)
                            ->disabled(fn (?Role $record): bool => $record !== null && in_array($record->name, \App\Support\Enums\StaffRole::executiveValues(), true))
                            ->dehydrated()
                            ->placeholder('e.g. branch-manager, cashier, cook')
                            ->helperText('Matching operational station responsibilities.'),
                    ]),

                Tabs::make('Permissions')
                    ->columnSpanFull()
                    ->tabs([
                        self::makePermissionTab(
                            title: 'Point of Sale',
                            icon: 'heroicon-o-computer-desktop',
                            field: 'permissions_pos',
                            permissions: self::PERMISSION_TABS['permissions_pos'],
                        ),
                        self::makePermissionTab(
                            title: 'Floor & Kitchen',
                            icon: 'heroicon-o-fire',
                            field: 'permissions_operations',
                            permissions: self::PERMISSION_TABS['permissions_operations'],
                        ),
                        self::makePermissionTab(
                            title: 'Backoffice & Admin',
                            icon: 'heroicon-o-lock-closed',
                            field: 'permissions_management',
                            permissions: self::PERMISSION_TABS['permissions_management'],
                        ),
                    ]),
            ]);
    }

    /**
     * Populate detached permission tab fields from the existing role record.
     */
    public static function fillPermissionTabs(array $data, Role $record): array
    {
        $rolePermissions = $record->permissions()->pluck('name')->toArray();

        foreach (self::PERMISSION_TABS as $field => $tabPermissions) {
            $data[$field] = array_values(array_intersect($rolePermissions, $tabPermissions));
        }

        return $data;
    }

    /**
     * Extract combined permissions from tab fields and strip temporary keys from role data.
     *
     * @return array{0: array<string, mixed>, 1: list<string>}
     */
    public static function extractPermissions(array $data): array
    {
        $permissions = [];

        foreach (array_keys(self::PERMISSION_TABS) as $field) {
            $permissions = array_merge($permissions, $data[$field] ?? []);
            unset($data[$field]);
        }

        $unique = array_values(array_unique($permissions));
        $valid = empty($unique)
            ? []
            : Permission::query()->whereIn('name', $unique)->pluck('name')->all();

        return [$data, $valid];
    }

    /**
     * Build a single permission tab with labeled, described 2-column checkboxes.
     *
     * @param  list<string>  $permissions
     */
    private static function makePermissionTab(string $title, string $icon, string $field, array $permissions): Tab
    {
        return Tab::make($title)
            ->icon($icon)
            ->schema([
                CheckboxList::make($field)
                    ->hiddenLabel()
                    ->options(function () use ($permissions): array {
                        $existing = Permission::query()
                            ->whereIn('name', $permissions)
                            ->pluck('name')
                            ->all();

                        $options = [];
                        foreach ($permissions as $permission) {
                            if (in_array($permission, $existing, true)) {
                                $label = PermissionEnum::label($permission);
                                $options[$permission] = $permission === 'financial.control' ? $label . ' ⚠️' : $label;
                            }
                        }

                        return $options;
                    })
                    ->descriptions(function () use ($permissions): array {
                        $descriptions = [];
                        foreach ($permissions as $permission) {
                            $descriptions[$permission] = PermissionEnum::description($permission);
                        }

                        return $descriptions;
                    })
                    ->bulkToggleable()
                    ->columns(['default' => 1, 'md' => 2]),
            ]);
    }
}
