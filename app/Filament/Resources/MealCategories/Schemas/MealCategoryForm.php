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
                    ->extraInputAttributes(fn ($record) => $record ? [] : [
                        'x-on:input' => '
                            const slug = ($el.value || "")
                                .toLowerCase()
                                .trim()
                                .replace(/[^a-z0-9\s-]/g, "")
                                .replace(/[\s_-]+/g, "-")
                                .replace(/^-+|-+$/g, "");
                            const slugInput = $el.closest("form")?.querySelector("[data-category-slug-input]");
                            if (slugInput) {
                                slugInput.value = slug;
                                slugInput.dispatchEvent(new Event("input", { bubbles: true }));
                            }
                        ',
                    ]),

                TextInput::make('slug')
                    ->required()
                    ->maxLength(100)
                    ->extraInputAttributes(['data-category-slug-input' => 'true']),

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
