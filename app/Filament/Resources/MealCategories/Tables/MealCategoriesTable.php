<?php

namespace App\Filament\Resources\MealCategories\Tables;

use App\Domains\Menu\Models\MealCategory;
use App\Filament\Resources\MealCategories\MealCategoryResource;
use App\Support\Constants\SystemConstants;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class MealCategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('display_order')
                    ->label('#')
                    ->numeric()
                    ->sortable()
                    ->alignment('center'),
                TextColumn::make('name')
                    ->label('Category Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('slug')
                    ->label('Slug')
                    ->fontFamily('mono')
                    ->searchable(),
                TextColumn::make('meals_count')
                    ->label('Dishes')
                    ->alignment('center')
                    ->badge(),
                TextColumn::make('branch.name')
                    ->label('Branch')
                    ->default('Global (All Branches)')
                    ->searchable()
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('display_order', 'asc')
            ->filters([
                SelectFilter::make('branch_id')
                    ->relationship('branch', 'name')
                    ->label('Branch'),
                TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->defaultPaginationPageOption(SystemConstants::DEFAULT_TABLE_PAGE_SIZE)
            ->recordAction(null)
            ->recordActions([
                EditAction::make()
                    ->modal()
                    ->modalWidth('md')
                    ->visible(fn (MealCategory $record): bool => MealCategoryResource::canEdit($record))
                    ->authorize(fn (MealCategory $record): bool => MealCategoryResource::canEdit($record)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->authorizeIndividualRecords()
                        ->authorize(fn (): bool => MealCategoryResource::canDeleteAny()),
                ]),
            ]);
    }
}
