<?php

namespace App\Filament\Resources\Roles\Schemas;

use App\Support\Enums\PermissionEnum;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class RoleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Role Name')
                    ->required()
                    ->maxLength(255)
                    ->unique('roles', 'name', ignoreRecord: true)
                    ->placeholder('e.g. shift-supervisor'),

                CheckboxList::make('permissions')
                    ->label('Assigned System Permissions')
                    ->relationship('permissions', 'name')
                    ->getOptionLabelFromRecordUsing(fn ($record) => PermissionEnum::label($record->name))
                    ->columns(2)
                    ->searchable(),
            ]);
    }
}
