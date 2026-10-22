<?php

namespace App\Filament\Resources\Users\Tables;

use App\Domains\Identity\Actions\ToggleUserActiveStatusAction;
use App\Domains\Identity\Models\User;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
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

                TextColumn::make('is_active')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Active' : 'Deactivated')
                    ->color(fn (bool $state): string => $state ? 'success' : 'danger')
                    ->sortable(),

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
                TernaryFilter::make('is_active')
                    ->label('Account Status')
                    ->placeholder('All Staff')
                    ->trueLabel('Active Staff Only')
                    ->falseLabel('Deactivated Staff Only'),

                SelectFilter::make('roles')
                    ->relationship('roles', 'name')
                    ->label('Role'),

                SelectFilter::make('branch_id')
                    ->relationship('branch', 'name')
                    ->label('Branch'),
            ])
            ->recordActions([
                Action::make('deactivate')
                    ->label('Deactivate')
                    ->icon('heroicon-o-user-minus')
                    ->color('danger')
                    ->visible(fn (User $record): bool => (bool) $record->is_active)
                    ->disabled(fn (User $record): bool => $record->id === auth()->id())
                    ->requiresConfirmation()
                    ->modalHeading('Deactivate Staff Account')
                    ->modalDescription(fn (User $record): string => "Are you sure you want to deactivate {$record->name}'s account? They will be immediately blocked from logging into POS terminals and back-office portals.")
                    ->action(function (User $record) {
                        app(ToggleUserActiveStatusAction::class)->execute($record);
                        Notification::make()
                            ->title('User Deactivated')
                            ->body("{$record->name}'s account has been deactivated.")
                            ->warning()
                            ->send();
                    }),

                Action::make('reactivate')
                    ->label('Reactivate')
                    ->icon('heroicon-o-user-plus')
                    ->color('success')
                    ->visible(fn (User $record): bool => ! (bool) $record->is_active)
                    ->requiresConfirmation()
                    ->modalHeading('Reactivate Staff Account')
                    ->modalDescription(fn (User $record): string => "Reactivate {$record->name}'s account? They will regain access to their assigned station and permissions.")
                    ->action(function (User $record) {
                        app(ToggleUserActiveStatusAction::class)->execute($record);
                        Notification::make()
                            ->title('User Reactivated')
                            ->body("{$record->name}'s account has been reactivated.")
                            ->success()
                            ->send();
                    }),

                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('deactivate_selected')
                        ->label('Deactivate Selected')
                        ->icon('heroicon-o-user-minus')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Deactivate Selected Accounts')
                        ->modalDescription('Are you sure you want to deactivate the selected staff accounts? Active sessions will be terminated.')
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                            $toggleService = app(ToggleUserActiveStatusAction::class);
                            $count = 0;
                            foreach ($records as $record) {
                                if ($record->id !== auth()->id() && $record->is_active) {
                                    $toggleService->execute($record);
                                    $count++;
                                }
                            }
                            Notification::make()
                                ->title('Accounts Deactivated')
                                ->body("{$count} staff account(s) have been deactivated.")
                                ->warning()
                                ->send();
                        }),

                    BulkAction::make('reactivate_selected')
                        ->label('Reactivate Selected')
                        ->icon('heroicon-o-user-plus')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Reactivate Selected Accounts')
                        ->modalDescription('Are you sure you want to reactivate the selected staff accounts?')
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                            $toggleService = app(ToggleUserActiveStatusAction::class);
                            $count = 0;
                            foreach ($records as $record) {
                                if (! $record->is_active) {
                                    $toggleService->execute($record);
                                    $count++;
                                }
                            }
                            Notification::make()
                                ->title('Accounts Reactivated')
                                ->body("{$count} staff account(s) have been reactivated.")
                                ->success()
                                ->send();
                        }),

                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
