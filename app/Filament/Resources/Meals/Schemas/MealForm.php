<?php

namespace App\Filament\Resources\Meals\Schemas;

use App\Support\Constants\SystemConstants;
use App\Support\Services\CodeGeneratorService;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class MealForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('category_id')
                    ->label('Menu Category')
                    ->relationship('category', 'name')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->live()
                    ->afterStateUpdated(function (?string $state, callable $set, $record) {
                        if (! $record && filled($state)) {
                            $set('menu_code', CodeGeneratorService::generateMenuCode((int) $state));
                        }
                    }),

                Select::make('branch_id')
                    ->label('Assigned Branch')
                    ->relationship('branch', 'name')
                    ->placeholder('All Branches (Global Menu)')
                    ->searchable()
                    ->preload()
                    ->default(null),

                TextInput::make('name')
                    ->label('Dish Name')
                    ->required()
                    ->maxLength(255),

                TextInput::make('menu_code')
                    ->label('Menu Code (Auto-Generated)')
                    ->placeholder('Auto-generated from category')
                    ->maxLength(50)
                    ->helperText('Automatically generated based on selected category.'),

                TextInput::make('price')
                    ->label('Selling Price')
                    ->required()
                    ->numeric()
                    ->prefix(SystemConstants::CURRENCY_SYMBOL)
                    ->minValue(0),

                Textarea::make('description')
                    ->label('Description & Prep Notes')
                    ->placeholder('Prep instructions, allergens, or ingredients')
                    ->rows(2)
                    ->columnSpanFull(),

                Toggle::make('is_available')
                    ->label('Available / In Stock')
                    ->default(true),

                Toggle::make('track_stock')
                    ->label('Track Limited Portions')
                    ->live(),

                TextInput::make('stock_quantity')
                    ->label('Available Portions')
                    ->numeric()
                    ->minValue(0)
                    ->placeholder('Portions count')
                    ->visible(fn ($get) => (bool) $get('track_stock')),

                Toggle::make('is_active')
                    ->label('Active in Menu')
                    ->default(true),

                TagsInput::make('kitchen_tags')
                    ->label('Kitchen Tags')
                    ->placeholder('Add tag (e.g. grill, fry, vegan)')
                    ->columnSpanFull(),

                Hidden::make('created_by')
                    ->default(fn () => auth()->id()),
            ]);
    }
}
