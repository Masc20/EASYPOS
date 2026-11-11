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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class UserResource extends Resource
{
    public static function canAccess(): bool
    {
        $user = auth()->user();

        return (bool) ($user && ($user->isOwner() || $user->can('users.manage') || $user->can('admin.access')));
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
            $query->where('branch_id', $user->branch_id)
                ->whereHas('roles', fn ($q) => $q->whereIn('name', StaffRole::floorValues()));
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
            if ((int) $record->id === (int) $user->id) {
                return false;
            }
            if ((int) $record->branch_id !== (int) $user->branch_id) {
                return false;
            }
            if (! $record->hasAnyRole(StaffRole::floorValues())) {
                return false;
            }
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
            if ((int) $record->id === (int) $user->id) {
                return false;
            }
            if ((int) $record->branch_id !== (int) $user->branch_id) {
                return false;
            }
            if (! $record->hasAnyRole(StaffRole::floorValues())) {
                return false;
            }
        }

        return true;
    }
}
