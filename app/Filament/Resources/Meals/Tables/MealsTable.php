<?php

namespace App\Filament\Resources\Meals\Tables;

use App\Domains\Menu\Models\Meal;
use App\Support\Constants\SystemConstants;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class MealsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('menu_code')
                    ->label('Code')
                    ->fontFamily('mono')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Dish Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('category.name')
                    ->label('Category')
                    ->searchable()
                    ->sortable()
                    ->badge(),
                TextColumn::make('branch.name')
                    ->label('Branch')
                    ->default('Global (All Branches)')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('price')
                    ->label('Price')
                    ->money(SystemConstants::CURRENCY_CODE)
                    ->sortable()
                    ->alignment('right'),
                TextColumn::make('stock_quantity')
                    ->label('Portions')
                    ->numeric()
                    ->sortable()
                    ->placeholder('Unlimited')
                    ->alignment('center'),
                IconColumn::make('is_available')
                    ->label('Available')
                    ->boolean(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('category_id')
                    ->relationship('category', 'name')
                    ->label('Category'),
                SelectFilter::make('branch_id')
                    ->relationship('branch', 'name')
                    ->label('Branch')
                    ->visible(fn () => ! auth()->user()?->isBranchScoped()),
                TernaryFilter::make('is_available')
                    ->label('In Stock'),
                TrashedFilter::make(),
            ])
            ->defaultPaginationPageOption(SystemConstants::DEFAULT_TABLE_PAGE_SIZE)
            ->recordAction(null)
            ->recordActions([
                EditAction::make()
                    ->modal()
                    ->modalWidth('2xl')
                    ->visible(function (Meal $record): bool {
                        $user = auth()->user();
                        if (! $user) {
                            return false;
                        }
                        if ($user->isOwner()) {
                            return true;
                        }
                        if ($user->isBranchScoped()) {
                            return $record->branch_id !== null && (int) $record->branch_id === (int) $user->branch_id;
                        }
                        return true;
                    })
                    ->authorize(function (Meal $record): bool {
                        $user = auth()->user();
                        if (! $user) {
                            return false;
                        }
                        if ($user->isOwner()) {
                            return true;
                        }
                        if ($user->isBranchScoped()) {
                            return $record->branch_id !== null && (int) $record->branch_id === (int) $user->branch_id;
                        }
                        return true;
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                            $user = auth()->user();
                            foreach ($records as $record) {
                                if ($user && $user->isBranchScoped()) {
                                    if ($record->branch_id === null || (int) $record->branch_id !== (int) $user->branch_id) {
                                        continue;
                                    }
                                }
                                $record->delete();
                            }
                        }),
                    ForceDeleteBulkAction::make()
                        ->visible(fn () => ! auth()->user()?->isBranchScoped()),
                    RestoreBulkAction::make()
                        ->visible(fn () => ! auth()->user()?->isBranchScoped()),
                ]),
            ]);
    }
}
