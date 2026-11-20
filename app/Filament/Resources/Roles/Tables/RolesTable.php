<?php

namespace App\Filament\Resources\Roles\Tables;

use App\Domains\Audit\Services\ActivityLogger;
use App\Filament\Resources\Roles\RoleResource;
use App\Filament\Resources\Roles\Schemas\RoleForm;
use App\Support\Enums\PermissionEnum;
use App\Support\Enums\StaffRole;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Spatie\Permission\Models\Role;

class RolesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Role Name')
                    ->formatStateUsing(fn (string $state) => str_replace(['-', '_'], ' ', ucwords($state, '-_')))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('permissions.name')
                    ->label('Assigned Capabilities')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => PermissionEnum::label($state))
                    ->searchable()
                    ->toggleable()
                    ->width('80%')
                    ->wrap(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('name')
                    ->label('Role Name')
                    ->options(fn () => \Spatie\Permission\Models\Role::pluck('name', 'name')->toArray()),
            ])
            ->recordAction(null)
            ->recordActions([
                EditAction::make()
                    ->databaseTransaction()
                    ->modal()
                    ->modalWidth('4xl')
                    ->visible(fn (Role $record): bool => (bool) (RoleResource::canAccess() && (auth()->user()?->hasRole(StaffRole::OWNER->value) || ! in_array($record->name, StaffRole::executiveValues(), true))))
                    ->authorize(fn (Role $record): bool => (bool) (RoleResource::canAccess() && (auth()->user()?->hasRole(StaffRole::OWNER->value) || ! in_array($record->name, StaffRole::executiveValues(), true))))
                    ->mutateRecordDataUsing(fn (array $data, Role $record): array => RoleForm::fillPermissionTabs($data, $record))
                    ->using(function (Role $record, array $data): Role {
                        abort_unless(RoleResource::canAccess(), 403);
                        if (in_array($record->name, StaffRole::executiveValues(), true) && ! auth()->user()?->hasRole(StaffRole::OWNER->value)) {
                            abort(403, 'Only the System Owner can modify executive system roles.');
                        }

                        [$cleanData, $permissions] = RoleForm::extractPermissions($data);
                        if (in_array($record->name, StaffRole::executiveValues(), true)) {
                            $cleanData['name'] = $record->name;
                        }
                        $record->update($cleanData);
                        $record->syncPermissions($permissions);

                        ActivityLogger::success(
                            category: 'security',
                            event: 'role_updated',
                            description: "Updated role '{$record->name}'.",
                            subject: $record,
                            metadata: ['role' => $record->name, 'permissions' => $permissions],
                        );

                        return $record;
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->authorize(fn (): bool => RoleResource::canDeleteAny())
                        ->action(function (Collection $records): void {
                            abort_unless(RoleResource::canDeleteAny(), 403);
                            foreach ($records as $record) {
                                if (! $record instanceof Role) {
                                    continue;
                                }
                                if (in_array($record->name, StaffRole::executiveValues(), true)) {
                                    continue;
                                }
                                $roleName = $record->name;
                                $record->delete();

                                ActivityLogger::success(
                                    category: 'security',
                                    event: 'role_deleted',
                                    description: "Deleted role '{$roleName}'.",
                                    metadata: ['role' => $roleName],
                                );
                            }
                        }),
                ]),
            ]);
    }
}
