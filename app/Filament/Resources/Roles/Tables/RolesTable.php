<?php

namespace App\Filament\Resources\Roles\Tables;

use App\Filament\Resources\Roles\Schemas\RoleForm;
use App\Support\Enums\PermissionEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
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
                    ->modal()
                    ->modalWidth('4xl')
                    ->mutateRecordDataUsing(fn (array $data, Role $record): array => RoleForm::fillPermissionTabs($data, $record))
                    ->using(function (Role $record, array $data): Role {
                        [$cleanData, $permissions] = RoleForm::extractPermissions($data);
                        $record->update($cleanData);
                        $record->syncPermissions($permissions);

                        return $record;
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
