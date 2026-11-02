<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Support\Services\CodeGeneratorService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Spatie\Permission\Models\Role;

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

                Select::make('branch_id')
                    ->label('Assigned Branch')
                    ->relationship('branch', 'name')
                    ->placeholder('Global / HQ (All Branches)')
                    ->searchable()
                    ->preload()
                    ->default(null)
                    ->live()
                    ->afterStateUpdated(function ($state, callable $set, callable $get, $record) {
                        if (! $record) {
                            $roleIds = (array) $get('roles');
                            $roleName = ! empty($roleIds) ? (Role::find($roleIds[0])?->name ?? 'staff') : 'staff';
                            $set('emp_id', CodeGeneratorService::generateEmpId($state ? (int) $state : null, $roleName));
                        }
                    }),

                Select::make('roles')
                    ->label('Position / Role')
                    ->relationship('roles', 'name')
                    ->multiple()
                    ->preload()
                    ->searchable()
                    ->required()
                    ->live()
                    ->afterStateUpdated(function ($state, callable $set, callable $get, $record) {
                        if (! $record && ! empty($state)) {
                            $firstRoleId = is_array($state) ? reset($state) : $state;
                            $roleName = Role::find($firstRoleId)?->name ?? 'staff';
                            $branchId = $get('branch_id') ? (int) $get('branch_id') : null;
                            $set('emp_id', CodeGeneratorService::generateEmpId($branchId, $roleName));
                        }
                    }),

                TextInput::make('emp_id')
                    ->label('Employee ID (Auto-Generated)')
                    ->default(fn () => CodeGeneratorService::generateEmpId(null, 'cashier'))
                    ->maxLength(50)
                    ->unique('users', 'emp_id', ignoreRecord: true)
                    ->helperText('Auto-generated from Branch + Position + Sequence.'),

                TextInput::make('email')
                    ->label('Email Address (Back-Office Only)')
                    ->email()
                    ->placeholder('Required for Admin / Manager login')
                    ->maxLength(255)
                    ->unique('users', 'email', ignoreRecord: true),

                TextInput::make('pin_code')
                    ->label('Floor Terminal 4-Digit PIN')
                    ->password()
                    ->revealable()
                    ->maxLength(4)
                    ->minLength(4)
                    ->rule('regex:/^[0-9]{4}$/')
                    ->placeholder('e.g. 1234')
                    ->helperText('Used for floor terminal sign-in or manager void approvals.')
                    ->dehydrated(fn ($state) => filled($state)),

                TextInput::make('password')
                    ->label('Back-Office Password')
                    ->password()
                    ->revealable()
                    ->minLength(8)
                    ->placeholder('Leave blank to keep existing password')
                    ->helperText('Used for back-office /admin portal login.')
                    ->dehydrated(fn ($state) => filled($state)),

                Toggle::make('is_active')
                    ->label('Account Active Status')
                    ->default(true)
                    ->disabled(fn ($record) => $record && $record->id === auth()->id()),
            ]);
    }
}
