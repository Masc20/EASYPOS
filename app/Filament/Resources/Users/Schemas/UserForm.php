<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Support\Enums\StaffRole;
use App\Support\Services\CodeGeneratorService;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        $user = auth()->user();
        $isBranchScoped = $user && $user->isBranchScoped();

        // Determine if user can manage passwords (admins/owners only)
        $canManagePassword = $user && ($user->isOwner() || $user->can('users.manage'));

        return $schema
            ->components([

                // Name — always visible
                TextInput::make('name')
                    ->label('Full Name')
                    ->required()
                    ->maxLength(255),

                // Branch — hidden for branch-scoped, select for HQ
                $isBranchScoped
                    ? Hidden::make('branch_id')
                        ->default($user->branch_id)
                        ->dehydrateStateUsing(fn () => $user->branch_id)
                    : Select::make('branch_id')
                        ->label('Assigned Branch')
                        ->relationship('branch', 'name')
                        ->placeholder('Global / HQ (All Branches)')
                        ->preload()
                        ->default(null)
                        ->live(debounce: 300)
                        ->afterStateUpdated(function ($state, callable $set, callable $get, $record) use ($isBranchScoped) {
                            $branchId = filled($state) ? (int) $state : null;
                            $roleIds = (array) ($get('roles') ?? []);
                            $roleName = ! empty($roleIds)
                                ? (Role::whereIn('id', $roleIds)->value('name') ?? StaffRole::CASHIER->value)
                                : ($record?->roles?->first()?->name ?? StaffRole::CASHIER->value);

                            if ($record && $record->branch_id === $branchId && $record->hasRole($roleName) && filled($record->emp_id)) {
                                $set('emp_id', $record->emp_id);
                                return;
                            }

                            // Auto-generate employee ID based on branch and role
                            $set('emp_id', CodeGeneratorService::generateEmpId($branchId, $roleName));
                        }),

                // Roles — floor values for branch-scoped, all for HQ
                Select::make('roles')
                    ->label('Position / Role')
                    ->relationship(
                        'roles',
                        'name',
                        modifyQueryUsing: $isBranchScoped
                            ? fn (Builder $query) => $query->whereIn('name', StaffRole::floorValues())
                            : null
                    )
                    ->multiple()
                    ->preload()
                    ->required()
                    ->live(debounce: 300)
                    ->afterStateUpdated(function ($state, callable $set, callable $get, $record) use ($isBranchScoped, $user) {
                        $branchValue = $isBranchScoped ? $user->branch_id : $get('branch_id');
                        $branchId = filled($branchValue) ? (int) $branchValue : null;
                        $roleIds = (array) ($state ?? []);
                        $roleName = ! empty($roleIds)
                            ? (Role::whereIn('id', $roleIds)->value('name') ?? StaffRole::CASHIER->value)
                            : ($record?->roles?->first()?->name ?? StaffRole::CASHIER->value);

                        if ($record && $record->branch_id === $branchId && $record->hasRole($roleName) && filled($record->emp_id)) {
                            $set('emp_id', $record->emp_id);
                            return;
                        }

                        // Auto-generate employee ID based on branch and role
                        $set('emp_id', CodeGeneratorService::generateEmpId($branchId, $roleName));
                    }),

                // Employee ID — auto-generated based on branch + role
                TextInput::make('emp_id')
                    ->label('Employee ID')
                    ->default(fn ($record) => $record ? null : CodeGeneratorService::generateEmpId($isBranchScoped ? $user->branch_id : null, StaffRole::CASHIER->value))
                    ->maxLength(50)
                    ->unique('users', 'emp_id', ignoreRecord: true)
                    ->disabled(!$canManagePassword),

                // Email — only for HQ/non-branch-scoped users
                TextInput::make('email')
                    ->label('Email Address')
                    ->email()
                    ->placeholder('Required for Admin / Manager login')
                    ->maxLength(255)
                    ->unique('users', 'email', ignoreRecord: true)
                    ->visible(!$isBranchScoped),

                // PIN Code — optional generation via button, only shown for branch-scoped
                TextInput::make('pin_code')
                    ->label('Floor Terminal PIN')
                    ->password()
                    ->revealable()
                    ->maxLength(4)
                    ->minLength(4)
                    ->rule('regex:/^[0-9]{4}$/')
                    ->placeholder('4-Digit PIN')
                    ->dehydrated(fn ($state) => filled($state))
                    ->visible($isBranchScoped)
                    ->suffixAction(
                        Action::make('generate_pin')
                            ->icon('heroicon-o-arrow-path')
                            ->tooltip('Generate random 4-digit PIN')
                            ->action(function (callable $set) use ($user) {
                                $set('pin_code', CodeGeneratorService::generatePin($user->branch_id ?? null));
                            })
                    ),

                // Back-Office Password — ONLY for admins/owners, with generate button
                $canManagePassword
                    ? TextInput::make('password')
                        ->label('Back-Office Password')
                        ->password()
                        ->revealable()
                        ->nullable()
                        ->rule(Password::min(12)->letters()->mixedCase()->numbers()->symbols()->uncompromised())
                        ->placeholder('Password')
                        ->dehydrated(fn ($state) => filled($state))
                        ->visible($user->isOwner() || $user->can('users.manage'))
                        ->suffixAction(
                            Action::make('generate_password')
                                ->icon('heroicon-o-arrow-path')
                                ->tooltip('Generate secure password')
                                ->action(function (callable $set) {
                                    $set('password', CodeGeneratorService::generatePassword());
                                })
                        )
                    : null,

                // Active toggle
                Toggle::make('is_active')
                    ->label('Active Account')
                    ->default(true),
            ]);
    }
}