<?php

namespace App\Filament\Resources\Users\Pages;

use App\Domains\Branches\Models\Branch;
use App\Domains\Identity\Models\User;
use App\Filament\Resources\Users\UserResource;
use App\Support\Enums\StaffRole;
use App\Support\Services\CodeGeneratorService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // 1. Dedicated Modal for Floor Staff (PIN Authentication)
            Action::make('create_floor_staff')
                ->label('Add Floor Staff (PIN)')
                ->icon('heroicon-o-identification')
                ->color('primary')
                ->modalHeading('Add Floor Staff Member')
                ->modalDescription('Floor staff sign in to operational terminals (POS, Kitchen, Chef Menu) using their Employee ID and 4-digit PIN.')
                ->modalWidth('lg')
                ->schema([
                    TextInput::make('name')
                        ->label('Staff Full Name')
                        ->required()
                        ->maxLength(255),

                    Select::make('branch_id')
                        ->label('Assigned Branch')
                        ->options(fn () => Branch::where('is_active', true)->pluck('name', 'id')->toArray())
                        ->required()
                        ->searchable()
                        ->live()
                        ->afterStateUpdated(function ($state, callable $set, callable $get) {
                            $role = $get('role') ?: StaffRole::CASHIER->value;
                            $set('emp_id', CodeGeneratorService::generateEmpId($state ? (int) $state : null, $role));
                        }),

                    Select::make('role')
                        ->label('Station Position')
                        ->options(StaffRole::floorOptions())
                        ->default(StaffRole::CASHIER->value)
                        ->required()
                        ->live()
                        ->afterStateUpdated(function ($state, callable $set, callable $get) {
                            $branchId = $get('branch_id') ? (int) $get('branch_id') : null;
                            $set('emp_id', CodeGeneratorService::generateEmpId($branchId, $state ?: StaffRole::CASHIER->value));
                        }),

                    TextInput::make('emp_id')
                        ->label('Employee ID (Auto-Generated)')
                        ->default(fn () => CodeGeneratorService::generateEmpId(1, StaffRole::CASHIER->value))
                        ->required()
                        ->maxLength(50)
                        ->unique('users', 'emp_id')
                        ->helperText('Auto-generated based on Branch + Position + Sequence.'),

                    TextInput::make('pin_code')
                        ->label('4-Digit Terminal PIN')
                        ->password()
                        ->revealable()
                        ->required()
                        ->maxLength(4)
                        ->minLength(4)
                        ->rule('regex:/^[0-9]{4}$/')
                        ->placeholder('e.g. 1234'),

                    Toggle::make('is_active')
                        ->label('Active Account')
                        ->default(true),
                ])
                ->action(function (array $data) {
                    $empId = filled($data['emp_id'] ?? null)
                        ? $data['emp_id']
                        : CodeGeneratorService::generateEmpId((int) $data['branch_id'], $data['role']);

                    $user = User::create([
                        'name' => $data['name'],
                        'emp_id' => $empId,
                        'branch_id' => $data['branch_id'],
                        'pin_code' => $data['pin_code'],
                        'is_active' => $data['is_active'] ?? true,
                    ]);

                    $user->assignRole($data['role']);

                    Notification::make()
                        ->title('Floor Staff Created')
                        ->body("{$user->name} ({$user->emp_id}) has been added.")
                        ->success()
                        ->send();
                }),

            // 2. Dedicated Modal for Back-Office Admin / Manager (Email & Password)
            Action::make('create_admin_user')
                ->label('Add Admin / Manager')
                ->icon('heroicon-o-shield-check')
                ->color('gray')
                ->modalHeading('Add Back-Office Admin / Manager')
                ->modalDescription('Administrators and Branch Managers access the back-office portal using Email and Password.')
                ->modalWidth('lg')
                ->schema([
                    TextInput::make('name')
                        ->label('Full Name')
                        ->required()
                        ->maxLength(255),

                    TextInput::make('email')
                        ->label('Email Address')
                        ->email()
                        ->required()
                        ->maxLength(255)
                        ->unique('users', 'email'),

                    TextInput::make('password')
                        ->label('Back-Office Password')
                        ->password()
                        ->revealable()
                        ->required()
                        ->minLength(8),

                    Select::make('branch_id')
                        ->label('Assigned Branch')
                        ->options(fn () => Branch::where('is_active', true)->pluck('name', 'id')->toArray())
                        ->placeholder('Global / HQ (All Branches)')
                        ->searchable()
                        ->live()
                        ->afterStateUpdated(function ($state, callable $set, callable $get) {
                            $role = $get('role') ?: StaffRole::BRANCH_MANAGER->value;
                            $set('emp_id', CodeGeneratorService::generateEmpId($state ? (int) $state : null, $role));
                        }),

                    Select::make('role')
                        ->label('Administrative Role')
                        ->options(StaffRole::managementOptions())
                        ->default(StaffRole::BRANCH_MANAGER->value)
                        ->required()
                        ->live()
                        ->afterStateUpdated(function ($state, callable $set, callable $get) {
                            $branchId = $get('branch_id') ? (int) $get('branch_id') : null;
                            $set('emp_id', CodeGeneratorService::generateEmpId($branchId, $state ?: StaffRole::BRANCH_MANAGER->value));
                        }),

                    TextInput::make('emp_id')
                        ->label('Employee ID (Auto-Generated)')
                        ->default(fn () => CodeGeneratorService::generateEmpId(null, StaffRole::BRANCH_MANAGER->value))
                        ->required()
                        ->maxLength(50)
                        ->unique('users', 'emp_id')
                        ->helperText('Used when approving voids/refunds on floor terminals.'),

                    TextInput::make('pin_code')
                        ->label('Manager 4-Digit Approval PIN')
                        ->password()
                        ->revealable()
                        ->maxLength(4)
                        ->minLength(4)
                        ->rule('regex:/^[0-9]{4}$/')
                        ->placeholder('e.g. 9999 (For approving POS voids)'),

                    Toggle::make('is_active')
                        ->label('Active Account')
                        ->default(true),
                ])
                ->action(function (array $data) {
                    $empId = filled($data['emp_id'] ?? null)
                        ? $data['emp_id']
                        : CodeGeneratorService::generateEmpId($data['branch_id'] ? (int) $data['branch_id'] : null, $data['role']);

                    $user = User::create([
                        'name' => $data['name'],
                        'email' => $data['email'],
                        'password' => $data['password'],
                        'emp_id' => $empId,
                        'branch_id' => $data['branch_id'] ?? null,
                        'pin_code' => filled($data['pin_code'] ?? null) ? $data['pin_code'] : null,
                        'is_active' => $data['is_active'] ?? true,
                    ]);

                    $user->assignRole($data['role']);

                    Notification::make()
                        ->title('Admin / Manager Created')
                        ->body("{$user->name} ({$user->emp_id}) has been added.")
                        ->success()
                        ->send();
                }),
        ];
    }
}
