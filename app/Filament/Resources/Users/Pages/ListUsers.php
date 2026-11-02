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
        $updateEmpIdJs = '
            const form = $el.closest("form");
            if (!form) return;
            const mapEl = form.querySelector("[data-emp-map]");
            const branchEl = form.querySelector("[data-branch-select]");
            const roleEl = form.querySelector("[data-role-select]");
            const empInput = form.querySelector("[data-emp-id-input]");
            if (mapEl && empInput) {
                const map = JSON.parse(mapEl.dataset.empMap || "{}");
                const bId = branchEl && branchEl.value ? branchEl.value : "0";
                const role = roleEl && roleEl.value ? roleEl.value : "cashier";
                const code = map[bId + ":" + role];
                if (code) {
                    empInput.value = code;
                    empInput.dispatchEvent(new Event("input", { bubbles: true }));
                }
            }
        ';

        return [
            // 1. Dedicated Modal for Floor Staff (PIN-Based Terminal Access)
            Action::make('create_floor_staff')
                ->label('Add Floor Staff (PIN)')
                ->color('primary')
                ->modalHeading('Add Floor Staff (Terminal PIN)')
                ->modalWidth('lg')
                ->schema(function () use ($updateEmpIdJs) {
                    $empMap = CodeGeneratorService::getEmpIdPreviewMap();
                    $branches = Branch::where('is_active', true)->pluck('name', 'id')->toArray();
                    $firstBranchId = array_key_first($branches) ?? 1;
                    $defaultCode = $empMap["{$firstBranchId}:" . StaffRole::CASHIER->value]
                        ?? CodeGeneratorService::generateEmpId((int) $firstBranchId, StaffRole::CASHIER->value);

                    return [
                        TextInput::make('name')
                            ->label('Staff Full Name')
                            ->required()
                            ->maxLength(255),

                        Select::make('branch_id')
                            ->label('Assigned Branch')
                            ->options($branches)
                            ->default($firstBranchId)
                            ->required()
                            ->extraInputAttributes([
                                'data-branch-select' => 'true',
                                'x-on:change' => $updateEmpIdJs,
                            ]),

                        Select::make('role')
                            ->label('Station Position')
                            ->options(StaffRole::floorOptions())
                            ->default(StaffRole::CASHIER->value)
                            ->required()
                            ->extraInputAttributes([
                                'data-role-select' => 'true',
                                'x-on:change' => $updateEmpIdJs,
                            ]),

                        TextInput::make('emp_id')
                            ->label('Employee ID (Auto-Generated)')
                            ->default($defaultCode)
                            ->required()
                            ->maxLength(50)
                            ->unique('users', 'emp_id')
                            ->extraInputAttributes([
                                'data-emp-id-input' => 'true',
                                'data-emp-map' => json_encode($empMap),
                            ]),

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
                ->schema(function () use ($updateEmpIdJs) {
                    $empMap = CodeGeneratorService::getEmpIdPreviewMap();
                    $branches = Branch::where('is_active', true)->pluck('name', 'id')->toArray();
                    $defaultCode = $empMap['0:' . StaffRole::BRANCH_MANAGER->value]
                        ?? CodeGeneratorService::generateEmpId(null, StaffRole::BRANCH_MANAGER->value);

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
                            ->extraInputAttributes([
                                'data-branch-select' => 'true',
                                'x-on:change' => $updateEmpIdJs,
                            ]),

                        Select::make('role')
                            ->label('Administrative Role')
                            ->options(StaffRole::managementOptions())
                            ->default(StaffRole::BRANCH_MANAGER->value)
                            ->required()
                            ->extraInputAttributes([
                                'data-role-select' => 'true',
                                'x-on:change' => $updateEmpIdJs,
                            ]),

                        TextInput::make('emp_id')
                            ->label('Employee ID (Auto-Generated)')
                            ->default($defaultCode)
                            ->required()
                            ->maxLength(50)
                            ->unique('users', 'emp_id')
                            ->extraInputAttributes([
                                'data-emp-id-input' => 'true',
                                'data-emp-map' => json_encode($empMap),
                            ]),

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
