<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('emp_id')
                    ->label('Emp ID')
                    ->fontFamily('mono')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('name')
                    ->label('Staff Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable()
                    ->placeholder('Floor Staff (PIN only)'),

                TextColumn::make('roles.name')
                    ->label('Role(s)')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'owner', 'super-admin' => 'danger',
                        'branch-manager' => 'warning',
                        'cashier' => 'primary',
                        'chef', 'cook' => 'success',
                        default => 'gray',
                    }),

                TextColumn::make('branch.name')
                    ->label('Branch')
                    ->default('Global / All Branches')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('roles')
                    ->relationship('roles', 'name')
                    ->label('Role'),

                SelectFilter::make('branch_id')
                    ->relationship('branch', 'name')
                    ->label('Branch'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
