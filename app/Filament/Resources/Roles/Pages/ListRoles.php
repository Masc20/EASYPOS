<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Domains\Audit\Services\ActivityLogger;
use App\Filament\Resources\Roles\RoleResource;
use App\Filament\Resources\Roles\Schemas\RoleForm;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Spatie\Permission\Models\Role;

class ListRoles extends ListRecords
{
    use \App\Filament\Concerns\AuthorizesResourcePage;

    protected static string $resource = RoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->databaseTransaction()
                ->authorize(fn (): bool => RoleResource::canCreate())
                ->label('New Role')
                ->modal()
                ->modalWidth('4xl')
                ->using(function (array $data, string $model): Role {
                    abort_unless(RoleResource::canCreate(), 403);
                    [$cleanData, $permissions] = RoleForm::extractPermissions($data);
                    $record = $model::create($cleanData);
                    $record->syncPermissions($permissions);

                    ActivityLogger::success(
                        category: 'security',
                        event: 'role_created',
                        description: "Created role '{$record->name}'.",
                        subject: $record,
                        metadata: ['role' => $record->name, 'permissions' => $permissions],
                    );

                    return $record;
                }),
        ];
    }
}
