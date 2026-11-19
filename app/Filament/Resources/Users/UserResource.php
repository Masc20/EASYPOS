<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Schemas\UserForm;
use App\Filament\Resources\Users\Tables\UsersTable;
use App\Domains\Identity\Models\User;
use App\Support\Enums\StaffRole;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class UserResource extends Resource
{
    public static function canAccess(): bool
    {
        $user = auth()->user();

        return (bool) ($user
            && $user->canAccessAdmin()
            && ($user->isOwner() || $user->can('users.manage'))
            && (! $user->isBranchScoped() || $user->branch_id !== null));
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'Staff & Access Control';

    protected static ?string $navigationLabel = 'Staff & Users';

    protected static ?string $modelLabel = 'Staff User';

    protected static ?string $pluralModelLabel = 'Staff & Users';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return UserForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with(['roles', 'branch']);

        $user = auth()->user();
        if ($user && $user->isBranchScoped()) {
            $query->where('branch_id', $user->effectiveBranchId())
                ->whereHas('roles', fn ($q) => $q->whereIn('name', StaffRole::floorValues()));
        }

        return $query;
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        $user = auth()->user();
        if (! $user || (! $user->isOwner() && ! $user->can('users.manage'))) {
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
        if (! $user || (! $user->isOwner() && ! $user->can('users.manage'))) {
            return Response::deny('Unauthorized.');
        }

        if ($user->isOwner()) {
            return Response::allow();
        }

        if ($user->isBranchScoped()) {
            if ($user->branch_id === null || (int) $record->id === (int) $user->id) {
                return Response::deny('Unauthorized.');
            }
            if ($record->branch_id === null || (int) $record->branch_id !== $user->effectiveBranchId()) {
                return Response::deny('Unauthorized.');
            }
            if (! $record->hasAnyRole(StaffRole::floorValues())) {
                return Response::deny('Unauthorized.');
            }
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
