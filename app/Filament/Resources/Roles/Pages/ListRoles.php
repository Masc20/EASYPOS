<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Filament\Resources\Roles\RoleResource;
use App\Filament\Resources\Roles\Schemas\RoleForm;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Spatie\Permission\Models\Role;

class ListRoles extends ListRecords
{
    protected static string $resource = RoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('New Role')
                ->modal()
                ->modalWidth('4xl')
                ->using(function (array $data, string $model): Role {
                    [$cleanData, $permissions] = RoleForm::extractPermissions($data);
                    $record = $model::create($cleanData);
                    $record->syncPermissions($permissions);

                    return $record;
                }),
        ];
    }
}
