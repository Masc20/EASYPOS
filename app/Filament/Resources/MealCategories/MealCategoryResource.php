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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class MealCategoryResource extends Resource
{
    public static function canAccess(): bool
    {
        $user = auth()->user();

        return (bool) ($user && ($user->isOwner() || $user->can('menu.manage') || $user->can('admin.access')));
    }

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
        $configured = MealCategoriesTable::configure($table);

        $configured->getFilter('branch_id')?->visible(fn () => ! auth()->user()?->isBranchScoped());

        return $configured;
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
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with(['branch']);

        $user = auth()->user();
        if ($user && $user->isBranchScoped()) {
            $query->where(function (Builder $q) use ($user) {
                $q->where('branch_id', $user->branch_id)
                    ->orWhereNull('branch_id');
            });
        }

        return $query;
    }

    public static function canEdit(Model $record): bool
    {
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
    }

    public static function canDelete(Model $record): bool
    {
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
    }
}
