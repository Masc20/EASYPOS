<?php

namespace App\Filament\Resources\Meals;

use App\Domains\Menu\Models\Meal;
use App\Filament\Resources\Meals\Pages\CreateMeal;
use App\Filament\Resources\Meals\Pages\EditMeal;
use App\Filament\Resources\Meals\Pages\ListMeals;
use App\Filament\Resources\Meals\Schemas\MealForm;
use App\Filament\Resources\Meals\Tables\MealsTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class MealResource extends Resource
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

    protected static ?string $model = Meal::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Menu Management';

    protected static ?string $navigationLabel = 'Meals';

    protected static ?string $modelLabel = 'Meal';

    protected static ?string $pluralModelLabel = 'Meals';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return MealForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MealsTable::configure($table);
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
            'index' => ListMeals::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with(['category', 'branch']);

        $user = auth()->user();
        if ($user && $user->isBranchScoped()) {
            $query->forBranch($user->effectiveBranchId());
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

    public static function getRestoreAuthorizationResponse(Model $record): Response
    {
        $user = auth()->user();
        if (! $user || (! $user->isOwner() && ! $user->can('menu.manage')) || $user->isBranchScoped()) {
            return Response::deny('Unauthorized.');
        }

        return Response::allow();
    }

    public static function getRestoreAnyAuthorizationResponse(): Response
    {
        $user = auth()->user();
        if (! $user || (! $user->isOwner() && ! $user->can('menu.manage')) || $user->isBranchScoped()) {
            return Response::deny('Unauthorized.');
        }

        return Response::allow();
    }

    public static function getForceDeleteAuthorizationResponse(Model $record): Response
    {
        return static::getRestoreAuthorizationResponse($record);
    }

    public static function getForceDeleteAnyAuthorizationResponse(): Response
    {
        return static::getRestoreAnyAuthorizationResponse();
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

    public static function canRestore(Model $record): bool
    {
        return static::getRestoreAuthorizationResponse($record)->allowed();
    }

    public static function canRestoreAny(): bool
    {
        return static::getRestoreAnyAuthorizationResponse()->allowed();
    }

    public static function canForceDelete(Model $record): bool
    {
        return static::getForceDeleteAuthorizationResponse($record)->allowed();
    }

    public static function canForceDeleteAny(): bool
    {
        return static::getForceDeleteAnyAuthorizationResponse()->allowed();
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
