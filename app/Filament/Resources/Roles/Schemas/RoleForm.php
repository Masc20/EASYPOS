<?php

namespace App\Filament\Resources\Roles\Schemas;

use App\Support\Enums\PermissionEnum;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Actions\Action;

class RoleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                // Role Identifier — compact, single section
                Section::make('Role Identifier')
                    ->description('Lower-kebab-case slug matching operational responsibilities.')
                    ->schema([
                        TextInput::make('name')
                            ->label('Role Slug')
                            ->required()
                            ->maxLength(255)
                            ->unique('roles', 'name', ignoreRecord: true)
                            ->placeholder('e.g. branch-manager, cashier, cook')
                            ->helperText('Matching operational station responsibilities.'),
                    ]),

                // Permissions — grouped by functional area, 2-column layout
                Section::make('Permissions')
                    ->description('Select capabilities for this role.')
                    ->schema([

                        // Admin Menu — only for management roles
                        CheckboxList::make('permissions_admin_menu')
                            ->label('Admin Menu')
                            ->relationship(
                                name: 'permissions',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn ($query) => $query->whereIn('name', ['menu.manage'])
                            )
                            ->getOptionLabelFromRecordUsing(fn ($record) => PermissionEnum::label($record->name))
                            ->getOptionDescriptionFromRecordUsing(fn ($record) => PermissionEnum::description($record->name))
                            ->bulkToggleable()
                            ->columns(2),

                        // Floor Menu Catalog — for floor staff and managers
                        CheckboxList::make('permissions_floor_menu')
                            ->label('Floor Menu')
                            ->relationship(
                                name: 'permissions',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn ($query) => $query->whereIn('name', ['menu.view'])
                            )
                            ->getOptionLabelFromRecordUsing(fn ($record) => PermissionEnum::label($record->name))
                            ->getOptionDescriptionFromRecordUsing(fn ($record) => PermissionEnum::description($record->name))
                            ->bulkToggleable()
                            ->columns(2),

                        // Kitchen Display — for kitchen staff and managers
                        CheckboxList::make('permissions_kitchen')
                            ->label('Kitchen Display')
                            ->relationship(
                                name: 'permissions',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn ($query) => $query->whereIn('name', [
                                    'kitchen.view',
                                    'kitchen.status',
                                    'kitchen.prepare',
                                    'kitchen.manage',
                                ])
                            )
                            ->getOptionLabelFromRecordUsing(fn ($record) => PermissionEnum::label($record->name))
                            ->getOptionDescriptionFromRecordUsing(fn ($record) => PermissionEnum::description($record->name))
                            ->bulkToggleable()
                            ->columns(2),

                        // POS — for cashiers and managers
                        CheckboxList::make('permissions_pos')
                            ->label('POS (Point of Sale)')
                            ->relationship(
                                name: 'permissions',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn ($query) => $query->whereIn('name', [
                                    'pos.access',
                                    'pos.checkout',
                                    'pos.discount',
                                    'pos.refund',
                                ])
                            )
                            ->getOptionLabelFromRecordUsing(fn ($record) => PermissionEnum::label($record->name))
                            ->getOptionDescriptionFromRecordUsing(fn ($record) => PermissionEnum::description($record->name))
                            ->bulkToggleable()
                            ->columns(2),

                        // Management & Admin (⚠️ only for Owners/Super-Admins)
                        CheckboxList::make('permissions_management')
                            ->label('Management & Admin')
                            ->relationship(
                                name: 'permissions',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn ($query) => $query->whereIn('name', [
                                    'reports.view',
                                    'users.manage',
                                    'financial.control',
                                    'admin.access',
                                    'branch.manage',
                                    'branches.manage',
                                    'roles.manage',
                                ])
                            )
                            ->getOptionLabelFromRecordUsing(fn ($record) => PermissionEnum::label($record->name) . ($record->name === 'financial.control' ? ' ⚠️' : ''))
                            ->getOptionDescriptionFromRecordUsing(fn ($record) => PermissionEnum::description($record->name))
                            ->bulkToggleable()
                            ->columns(2),
                    ]),

                ]);
    }
}