<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Support\Enums\StaffRole;
use App\Support\Services\CodeGeneratorService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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

                Select::make('branch_id')
                    ->label('Assigned Branch')
                    ->relationship('branch', 'name')
                    ->placeholder('Global / HQ (All Branches)')
                    ->preload()
                    ->default(null)
                    ->live()
                    ->afterStateUpdated(function ($state, callable $set, callable $get, $record) {
                        $branchId = filled($state) ? (int) $state : null;
                        $roleIds = (array) ($get('roles') ?? []);
                        $roleName = ! empty($roleIds)
                            ? (\Spatie\Permission\Models\Role::whereIn('id', $roleIds)->value('name') ?? StaffRole::CASHIER->value)
                            : ($record?->roles?->first()?->name ?? StaffRole::CASHIER->value);

                        if ($record && $record->branch_id === $branchId && $record->hasRole($roleName) && filled($record->emp_id)) {
                            $set('emp_id', $record->emp_id);
                            return;
                        }

                        $set('emp_id', CodeGeneratorService::generateEmpId($branchId, $roleName));
                    }),

                Select::make('roles')
                    ->label('Position / Role')
                    ->relationship('roles', 'name')
                    ->multiple()
                    ->preload()
                    ->required()
                    ->live()
                    ->afterStateUpdated(function ($state, callable $set, callable $get, $record) {
                        $branchValue = $get('branch_id');
                        $branchId = filled($branchValue) ? (int) $branchValue : null;
                        $roleIds = (array) ($state ?? []);
                        $roleName = ! empty($roleIds)
                            ? (\Spatie\Permission\Models\Role::whereIn('id', $roleIds)->value('name') ?? StaffRole::CASHIER->value)
                            : ($record?->roles?->first()?->name ?? StaffRole::CASHIER->value);

                        if ($record && $record->branch_id === $branchId && $record->hasRole($roleName) && filled($record->emp_id)) {
                            $set('emp_id', $record->emp_id);
                            return;
                        }

                        $set('emp_id', CodeGeneratorService::generateEmpId($branchId, $roleName));
                    }),

                TextInput::make('emp_id')
                    ->label('Employee ID')
                    ->default(fn ($record) => $record ? null : CodeGeneratorService::generateEmpId(null, StaffRole::CASHIER->value))
                    ->maxLength(50)
                    ->unique('users', 'emp_id', ignoreRecord: true),

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
                    ->placeholder('Leave blank to keep existing PIN')
                    ->dehydrated(fn ($state) => filled($state)),

                TextInput::make('password')
                    ->label('Back-Office Password')
                    ->password()
                    ->revealable()
                    ->minLength(8)
                    ->placeholder('Leave blank to keep existing password')
                    ->dehydrated(fn ($state) => filled($state)),

                Toggle::make('is_active')
                    ->label('Active Account')
                    ->default(true),
            ]);
    }
}
