<?php

namespace App\Filament\Resources\Roles\Schemas;

use Filament\Forms\Components\Select;
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
                    ->unique('roles', 'name', ignoreRecord: true),

                Select::make('permissions')
                    ->label('Permissions')
                    ->multiple()
                    ->options(\Spatie\Permission\Models\Permission::pluck('name', 'name')
                        ->toArray())
                    ->searchable()
                    ->helperText('Select the permissions this role should have.'),
            ]);
    }
}