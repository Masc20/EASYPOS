<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Full Name')
                    ->required()
                    ->maxLength(255),

                TextInput::make('emp_id')
                    ->label('Employee ID')
                    ->placeholder('e.g. B01-CSH-002')
                    ->maxLength(50)
                    ->unique('users', 'emp_id', ignoreRecord: true),

                TextInput::make('email')
                    ->label('Email Address')
                    ->email()
                    ->placeholder('Required for Admin / Manager login')
                    ->maxLength(255)
                    ->unique('users', 'email', ignoreRecord: true),

                Select::make('branch_id')
                    ->label('Assigned Branch')
                    ->relationship('branch', 'name')
                    ->placeholder('Global / All Branches')
                    ->searchable()
                    ->preload()
                    ->default(null),

                Select::make('roles')
                    ->label('Assigned Role(s)')
                    ->relationship('roles', 'name')
                    ->multiple()
                    ->preload()
                    ->searchable()
                    ->required(),

                TextInput::make('pin_code')
                    ->label('Floor Terminal 4-Digit PIN')
                    ->password()
                    ->revealable()
                    ->maxLength(4)
                    ->minLength(4)
                    ->rule('regex:/^[0-9]{4}$/')
                    ->placeholder('e.g. 1234')
                    ->helperText('Used by Cashiers, Cooks, and Chefs to authenticate on floor terminals.')
                    ->dehydrated(fn ($state) => filled($state)),

                TextInput::make('password')
                    ->label('Admin Password')
                    ->password()
                    ->revealable()
                    ->minLength(8)
                    ->placeholder('Leave blank to keep existing password')
                    ->helperText('Used for backoffice /admin login.')
                    ->dehydrated(fn ($state) => filled($state)),
            ]);
    }
}
