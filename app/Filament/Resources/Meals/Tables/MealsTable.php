<?php

namespace App\Filament\Resources\Meals\Tables;

use App\Domains\Menu\Actions\DeleteMealAction;
use App\Domains\Menu\Models\Meal;
use App\Filament\Resources\Meals\MealResource;
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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

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
                    ->relationship(
                        'category',
                        'name',
                        modifyQueryUsing: function (Builder $query): Builder {
                            $user = auth()->user();

                            return ($user && $user->isBranchScoped())
                                ? $query->forBranch($user->effectiveBranchId())
                                : $query;
                        }
                    )
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
                    ->visible(fn (Meal $record): bool => MealResource::canEdit($record))
                    ->authorize(fn (Meal $record): bool => MealResource::canEdit($record)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label(fn () => auth()->user()?->isBranchScoped() ? 'Remove from branch' : 'Delete selected')
                        ->modalDescription(fn () => auth()->user()?->isBranchScoped()
                            ? 'Hide the selected meals from your branch. The global catalog keeps these meals.'
                            : 'Archive the selected meals from the catalog.')
                        ->using(function (DeleteBulkAction $action, Collection $records): void {
                            foreach ($records as $record) {
                                app(DeleteMealAction::class)->handle($record);
                            }
                        })
                        ->authorizeIndividualRecords()
                        ->authorize(fn (): bool => MealResource::canDeleteAny()),
                    ForceDeleteBulkAction::make()
                        ->visible(fn () => ! auth()->user()?->isBranchScoped())
                        ->authorize(fn (): bool => MealResource::canForceDeleteAny())
                        ->authorizeIndividualRecords(),
                    RestoreBulkAction::make()
                        ->visible(fn () => ! auth()->user()?->isBranchScoped())
                        ->authorize(fn (): bool => MealResource::canRestoreAny())
                        ->authorizeIndividualRecords(),
                ]),
            ]);
    }
}
