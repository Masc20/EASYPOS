<?php

namespace App\Filament\Resources\MealCategories;

use App\Domains\Menu\Models\MealCategory;
use App\Filament\Resources\MealCategories\Pages\CreateMealCategory;
use App\Filament\Resources\MealCategories\Pages\EditMealCategory;
use App\Filament\Resources\MealCategories\Pages\ListMealCategories;
use App\Filament\Resources\MealCategories\Schemas\MealCategoryForm;
use App\Filament\Resources\MealCategories\Tables\MealCategoriesTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class MealCategoryResource extends Resource
{
    protected static ?string $model = MealCategory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Menu Management';

    protected static ?string $navigationLabel = 'Categories';

    protected static ?string $modelLabel = 'Category';

    protected static ?string $pluralModelLabel = 'Categories';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return MealCategoryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MealCategoriesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMealCategories::route('/'),
            'create' => CreateMealCategory::route('/create'),
            'edit' => EditMealCategory::route('/{record}/edit'),
        ];
    }
}
