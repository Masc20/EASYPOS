<?php

namespace App\Filament\Resources\Users\Pages;

use App\Domains\Branches\Models\Branch;
use App\Domains\Identity\Models\User;
use App\Filament\Resources\Users\UserResource;
use App\Support\Enums\StaffRole;
use App\Support\Services\CodeGeneratorService;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
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
            // 1. Dedicated Modal for Floor Staff (PIN-Based Terminal Access)
            Action::make('create_floor_staff')
                ->label('Add Floor Staff (PIN)')
                ->color('primary')
                ->modalHeading('Add Floor Staff (Terminal PIN)')
                ->modalWidth('lg')
                ->schema(function () {
                    $currentUser = auth()->user();
                    $isBranchScoped = $currentUser && $currentUser->isBranchScoped();

                    $branches = $isBranchScoped
                        ? Branch::where('id', $currentUser->branch_id)->pluck('name', 'id')->toArray()
                        : Branch::where('is_active', true)->pluck('name', 'id')->toArray();
                    $defaultBranchId = $isBranchScoped ? $currentUser->branch_id : (array_key_first($branches) ?? 1);
                    $defaultCode = CodeGeneratorService::generateEmpId((int) $defaultBranchId, StaffRole::CASHIER->value);

                    return [
                        TextInput::make('name')
                            ->label('Staff Full Name')
                            ->required()
                            ->maxLength(255),

                        $isBranchScoped
                            ? Hidden::make('branch_id')
                                ->default($currentUser->branch_id)
                                ->dehydrateStateUsing(fn () => $currentUser->branch_id)
                            : Select::make('branch_id')
                                ->label('Assigned Branch')
                                ->options($branches)
                                ->default($defaultBranchId)
                                ->required()
                                ->live(debounce: 300)
                                ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                    $role = $get('role') ?: StaffRole::CASHIER->value;
                                    $set('emp_id', CodeGeneratorService::generateEmpId($state ? (int) $state : null, $role));
                                }),

                        Select::make('role')
                            ->label('Station Position')
                            ->options(StaffRole::floorOptions())
                            ->default(StaffRole::CASHIER->value)
                            ->required()
                            ->live(debounce: 300)
                            ->afterStateUpdated(function ($state, callable $set, callable $get) use ($isBranchScoped, $currentUser) {
                                $branchId = $isBranchScoped ? $currentUser->branch_id : $get('branch_id');
                                $role = $state ?: StaffRole::CASHIER->value;
                                $set('emp_id', CodeGeneratorService::generateEmpId($branchId ? (int) $branchId : null, $role));
                            }),

                        TextInput::make('emp_id')
                            ->label('Employee ID (Auto-Generated)')
                            ->default($defaultCode)
                            ->required()
                            ->maxLength(50)
                            ->unique('users', 'emp_id'),

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
                    ];
                })
                ->action(function (array $data) {
                    $currentUser = auth()->user();
                    if ($currentUser && $currentUser->isBranchScoped()) {
                        $data['branch_id'] = $currentUser->branch_id;
                        if (! in_array($data['role'], StaffRole::floorValues(), true)) {
                            abort(403, 'Unauthorized role assignment.');
                        }
                    }

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
                ->color('gray')
                ->modalHeading('Add Back-Office Admin / Manager')
                ->modalWidth('lg')
                ->visible(fn (): bool => (bool) (auth()->user()?->isOwner() || auth()->user()?->hasRole(StaffRole::SUPER_ADMIN->value)))
                ->schema(function () {
                    $branches = Branch::where('is_active', true)->pluck('name', 'id')->toArray();
                    $defaultCode = CodeGeneratorService::generateEmpId(null, StaffRole::BRANCH_MANAGER->value);

                    return [
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
                            ->options($branches)
                            ->placeholder('Global / HQ (All Branches)')
                            ->live(debounce: 300)
                            ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                $role = $get('role') ?: StaffRole::BRANCH_MANAGER->value;
                                $set('emp_id', CodeGeneratorService::generateEmpId(filled($state) ? (int) $state : null, $role));
                            }),

                        Select::make('role')
                            ->label('Administrative Role')
                            ->options(StaffRole::managementOptions())
                            ->default(StaffRole::BRANCH_MANAGER->value)
                            ->required()
                            ->live(debounce: 300)
                            ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                $branchId = $get('branch_id');
                                $role = $state ?: StaffRole::BRANCH_MANAGER->value;
                                $set('emp_id', CodeGeneratorService::generateEmpId(filled($branchId) ? (int) $branchId : null, $role));
                            }),

                        TextInput::make('emp_id')
                            ->label('Employee ID (Auto-Generated)')
                            ->default($defaultCode)
                            ->required()
                            ->maxLength(50)
                            ->unique('users', 'emp_id'),

                        TextInput::make('pin_code')
                            ->label('Manager 4-Digit Approval PIN')
                            ->password()
                            ->revealable()
                            ->maxLength(4)
                            ->minLength(4)
                            ->rule('regex:/^[0-9]{4}$/')
                            ->placeholder('Optional 4-digit PIN for POS void approvals'),

                        Toggle::make('is_active')
                            ->label('Active Account')
                            ->default(true),
                    ];
                })
                ->action(function (array $data) {
                    $currentUser = auth()->user();
                    if (! $currentUser || (! $currentUser->isOwner() && ! $currentUser->hasRole(StaffRole::SUPER_ADMIN->value))) {
                        abort(403, 'Unauthorized to create administrative users.');
                    }

                    $branchId = filled($data['branch_id'] ?? null) ? (int) $data['branch_id'] : null;
                    $empId = filled($data['emp_id'] ?? null)
                        ? $data['emp_id']
                        : CodeGeneratorService::generateEmpId($branchId, $data['role']);

                    $user = User::create([
                        'name' => $data['name'],
                        'email' => $data['email'],
                        'password' => $data['password'],
                        'emp_id' => $empId,
                        'branch_id' => $branchId,
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
