<?php

namespace App\Filament\Resources\MealCategories\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class MealCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('branch_id')
                    ->relationship('branch', 'name')
                    ->placeholder('All Branches (Global Category)')
                    ->preload()
                    ->default(null),

                TextInput::make('name')
                    ->required()
                    ->maxLength(100)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (?string $state, callable $set, $record) {
                        if (! $record && filled($state)) {
                            $set('slug', \Illuminate\Support\Str::slug($state));
                        }
                    }),

                TextInput::make('slug')
                    ->required()
                    ->maxLength(100),

                TextInput::make('display_order')
                    ->required()
                    ->numeric()
                    ->default(0),

                Toggle::make('is_active')
                    ->label('Active Category')
                    ->default(true),
            ]);
    }
}
