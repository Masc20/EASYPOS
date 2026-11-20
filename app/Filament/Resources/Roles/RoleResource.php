<?php

namespace App\Filament\Resources\Roles;

use App\Filament\Resources\Roles\Pages\ListRoles as ListRolesPage;
use App\Filament\Resources\Roles\Schemas\RoleForm;
use App\Filament\Resources\Roles\Tables\RolesTable;
use App\Support\Enums\StaffRole;
use App\Filament\Concerns\AuthorizesResourcePermissions;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class RoleResource extends Resource
{
    use AuthorizesResourcePermissions;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return (bool) ($user && $user->canAccessAdmin() && $user->can('roles.manage') && ! $user->isBranchScoped() && $user->hasAnyRole(StaffRole::executiveValues()));
    }

    public static function getEditAuthorizationResponse(\Illuminate\Database\Eloquent\Model $record): \Illuminate\Auth\Access\Response
    {
        if (! static::canAccess() || (in_array($record->name, StaffRole::executiveValues(), true) && ! auth()->user()->hasRole(StaffRole::OWNER->value))) {
            return \Illuminate\Auth\Access\Response::deny('Unauthorized.');
        }

        return static::getAuthorizationResponse('update', $record);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }
    protected static ?string $model = \Spatie\Permission\Models\Role::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Staff & Access Control';

    protected static ?string $navigationLabel = 'Roles & Permissions';

    protected static ?string $modelLabel = 'Role';

    protected static ?string $pluralModelLabel = 'Roles';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return RoleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RolesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRolesPage::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('guard_name', 'web')
            ->with(['permissions']);
    }
}
