<?php

namespace App\Filament\Resources\Roles\Tables;

use App\Support\Enums\PermissionEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

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
                    ->modalWidth('lg'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
