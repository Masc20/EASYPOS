<?php

namespace App\Filament\Resources\Roles\Schemas;

use App\Support\Enums\PermissionEnum;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RoleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Role Definition')
                    ->description('Define role identifier and station operational scope.')
                    ->schema([
                        TextInput::make('name')
                            ->label('Role Slug / Identifier')
                            ->required()
                            ->maxLength(255)
                            ->unique('roles', 'name', ignoreRecord: true)
                            ->placeholder('e.g. shift-supervisor, head-barista')
                            ->helperText('Lower-kebab-case identifier matching operational station responsibilities.'),
                    ]),

                Section::make('Admin Menu Setup & Configuration')
                    ->description('Filament back-office catalog: manage canonical menu items, prices, categories, and master availability.')
                    ->schema([
                        CheckboxList::make('permissions_admin_menu')
                            ->label('Admin Menu Setup Permissions')
                            ->relationship(
                                name: 'permissions',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn ($query) => $query->whereIn('name', ['menu.manage'])
                                    ->orderByRaw("CASE WHEN name = 'menu.manage' THEN 1 ELSE 99 END")
                            )
                            ->getOptionLabelFromRecordUsing(fn ($record) => PermissionEnum::label($record->name) . ' (' . PermissionEnum::scopeLabel($record->name) . ')')
                            ->getOptionDescriptionFromRecordUsing(fn ($record) => PermissionEnum::description($record->name))
                            ->bulkToggleable()
                            ->columns(1),
                    ]),

                Section::make('Floor Menu Catalog & Availability')
                    ->description('Floor-facing /menu terminal: view daily dish catalog, monitor portion counts, and mark items sold-out/86.')
                    ->schema([
                        CheckboxList::make('permissions_floor_menu')
                            ->label('Floor Menu Catalog Permissions')
                            ->relationship(
                                name: 'permissions',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn ($query) => $query->whereIn('name', ['menu.view'])
                                    ->orderByRaw("CASE WHEN name = 'menu.view' THEN 1 ELSE 99 END")
                            )
                            ->getOptionLabelFromRecordUsing(fn ($record) => PermissionEnum::label($record->name) . ' (' . PermissionEnum::scopeLabel($record->name) . ')')
                            ->getOptionDescriptionFromRecordUsing(fn ($record) => PermissionEnum::description($record->name))
                            ->bulkToggleable()
                            ->columns(1),
                    ]),

                Section::make('Kitchen Display & Ticket Management')
                    ->description('Kitchen Display System (/kitchen): queue visibility, ticket claiming, and preparation status progression.')
                    ->schema([
                        CheckboxList::make('permissions_kitchen')
                            ->label('Kitchen Display Permissions')
                            ->relationship(
                                name: 'permissions',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn ($query) => $query->whereIn('name', [
                                    'kitchen.view',
                                    'kitchen.status',
                                    'kitchen.prepare',
                                    'kitchen.manage',
                                ])->orderByRaw("CASE
                                    WHEN name = 'kitchen.view' THEN 1
                                    WHEN name = 'kitchen.status' THEN 2
                                    WHEN name = 'kitchen.prepare' THEN 3
                                    WHEN name = 'kitchen.manage' THEN 4
                                    ELSE 99
                                END")
                            )
                            ->getOptionLabelFromRecordUsing(fn ($record) => PermissionEnum::label($record->name) . ' (' . PermissionEnum::scopeLabel($record->name) . ')')
                            ->getOptionDescriptionFromRecordUsing(fn ($record) => PermissionEnum::description($record->name))
                            ->bulkToggleable()
                            ->columns(1),
                    ]),

                Section::make('Point of Sale (POS) Catalog & Checkout')
                    ->description('POS register terminal (/pos): ordering, payment collection, custom discount authorization, and void/refund approval.')
                    ->schema([
                        CheckboxList::make('permissions_pos')
                            ->label('Point of Sale (POS) Permissions')
                            ->relationship(
                                name: 'permissions',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn ($query) => $query->whereIn('name', [
                                    'pos.access',
                                    'pos.checkout',
                                    'pos.discount',
                                    'pos.refund',
                                ])->orderByRaw("CASE
                                    WHEN name = 'pos.access' THEN 1
                                    WHEN name = 'pos.checkout' THEN 2
                                    WHEN name = 'pos.discount' THEN 3
                                    WHEN name = 'pos.refund' THEN 4
                                    ELSE 99
                                END")
                            )
                            ->getOptionLabelFromRecordUsing(function ($record) {
                                $marker = in_array($record->name, ['pos.refund']) ? ' ⚠️' : '';
                                return PermissionEnum::label($record->name) . ' (' . PermissionEnum::scopeLabel($record->name) . ')' . $marker;
                            })
                            ->getOptionDescriptionFromRecordUsing(fn ($record) => PermissionEnum::description($record->name))
                            ->bulkToggleable()
                            ->columns(1),
                    ]),

                Section::make('Management, Reports & System Administration')
                    ->description('Executive governance, back-office portal access, branch isolation, staff administration, and financial reconciliation.')
                    ->schema([
                        CheckboxList::make('permissions_management')
                            ->label('Management & System Administration Permissions')
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
                                ])->orderByRaw("CASE
                                    WHEN name = 'reports.view' THEN 1
                                    WHEN name = 'users.manage' THEN 2
                                    WHEN name = 'financial.control' THEN 3
                                    WHEN name = 'admin.access' THEN 4
                                    WHEN name IN ('branch.manage', 'branches.manage') THEN 5
                                    WHEN name = 'roles.manage' THEN 6
                                    ELSE 99
                                END")
                            )
                            ->getOptionLabelFromRecordUsing(function ($record) {
                                $marker = in_array($record->name, ['financial.control']) ? ' ⚠️' : '';
                                return PermissionEnum::label($record->name) . ' (' . PermissionEnum::scopeLabel($record->name) . ')' . $marker;
                            })
                            ->getOptionDescriptionFromRecordUsing(fn ($record) => PermissionEnum::description($record->name))
                            ->bulkToggleable()
                            ->columns(1),
                    ]),
            ]);
    }
}
