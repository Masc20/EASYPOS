<?php

namespace App\Filament\Resources\Meals\Schemas;

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
                    ->relationship('category', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),
                Select::make('branch_id')
                    ->relationship('branch', 'name')
                    ->placeholder('All Branches (Global Menu)')
                    ->searchable()
                    ->preload()
                    ->default(null),
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('menu_code')
                    ->placeholder('e.g. M-01')
                    ->maxLength(50),
                TextInput::make('price')
                    ->required()
                    ->numeric()
                    ->prefix('₱')
                    ->minValue(0),
                TextInput::make('cost_price')
                    ->numeric()
                    ->prefix('₱')
                    ->minValue(0)
                    ->default(null),
                Textarea::make('description')
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
                    ->numeric()
                    ->minValue(0)
                    ->placeholder('Portions count')
                    ->visible(fn ($get) => (bool) $get('track_stock')),
                Toggle::make('is_active')
                    ->label('Active in Menu')
                    ->default(true),
                TagsInput::make('kitchen_tags')
                    ->placeholder('Add tag (e.g. grill, fry, vegan)')
                    ->columnSpanFull(),
                Hidden::make('created_by')
                    ->default(fn () => auth()->id()),
            ]);
    }
}
