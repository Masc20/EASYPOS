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
                    ->preload()
                    ->extraInputAttributes(fn ($record) => $record ? [] : [
                        'data-code-map' => json_encode(CodeGeneratorService::getCategoryMenuCodeMap()),
                        'x-on:change' => '
                            const map = JSON.parse($el.dataset.codeMap || "{}");
                            const code = map[$el.value];
                            if (code) {
                                const codeInput = $el.closest("form")?.querySelector("[data-menu-code-input]");
                                if (codeInput) {
                                    codeInput.value = code;
                                    codeInput.dispatchEvent(new Event("input", { bubbles: true }));
                                }
                            }
                        ',
                    ]),

                Select::make('branch_id')
                    ->label('Assigned Branch')
                    ->relationship('branch', 'name')
                    ->placeholder('All Branches (Global Menu)')
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
                    ->extraInputAttributes(['data-menu-code-input' => 'true']),

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
                    ->label('Track Limited Portions'),

                TextInput::make('stock_quantity')
                    ->label('Available Portions (If Tracked)')
                    ->numeric()
                    ->minValue(0)
                    ->placeholder('Leave blank for unlimited'),

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
