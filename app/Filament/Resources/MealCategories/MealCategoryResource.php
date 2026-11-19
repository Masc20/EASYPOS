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
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class MealCategoryResource extends Resource
{
    public static function canAccess(): bool
    {
        $user = auth()->user();

        return (bool) ($user
            && $user->canAccessAdmin()
            && ($user->isOwner() || $user->can('menu.manage'))
            && (! $user->isBranchScoped() || $user->branch_id !== null));
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
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
        $user = auth()->user();
        $isBranchScoped = (bool) ($user && $user->isBranchScoped());
        $effectiveBranchId = $isBranchScoped ? $user->effectiveBranchId() : null;

        $query = parent::getEloquentQuery()
            ->with(['branch'])
            ->withCount([
                'meals' => fn (Builder $q) => $isBranchScoped ? $q->forBranch($effectiveBranchId) : $q,
            ]);

        if ($isBranchScoped) {
            $query->forBranch($effectiveBranchId);
        }

        return $query;
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        $user = auth()->user();
        if (! $user || (! $user->isOwner() && ! $user->can('menu.manage'))) {
            return Response::deny('Unauthorized.');
        }

        if ($user->isBranchScoped() && $user->branch_id === null) {
            return Response::deny('Unauthorized.');
        }

        return Response::allow();
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        $user = auth()->user();
        if (! $user || (! $user->isOwner() && ! $user->can('menu.manage'))) {
            return Response::deny('Unauthorized.');
        }

        if ($user->isOwner()) {
            return Response::allow();
        }

        if ($user->isBranchScoped()) {
            return ($user->branch_id !== null && $record->branch_id !== null && (int) $record->branch_id === $user->effectiveBranchId())
                ? Response::allow()
                : Response::deny('Unauthorized.');
        }

        return Response::allow();
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return static::getEditAuthorizationResponse($record);
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return static::getCreateAuthorizationResponse();
    }

    public static function canCreate(): bool
    {
        return static::getCreateAuthorizationResponse()->allowed();
    }

    public static function canEdit(Model $record): bool
    {
        return static::getEditAuthorizationResponse($record)->allowed();
    }

    public static function canDelete(Model $record): bool
    {
        return static::getDeleteAuthorizationResponse($record)->allowed();
    }

    public static function canDeleteAny(): bool
    {
        return static::getDeleteAnyAuthorizationResponse()->allowed();
    }
}
