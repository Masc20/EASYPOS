<?php

namespace App\Filament\Concerns;

use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

trait AuthorizesResourcePermissions
{
    public static function getAuthorizationResponse(string $action, ?Model $record = null): Response
    {
        if (! static::canAccess() || ! in_array($action, static::allowedResourceActions(), true)) {
            return Response::deny('Unauthorized.');
        }

        return parent::getAuthorizationResponse($action, $record);
    }

    protected static function allowedResourceActions(): array
    {
        return ['viewAny', 'view', 'create', 'update', 'delete', 'deleteAny', 'restore', 'restoreAny', 'forceDelete', 'forceDeleteAny'];
    }
}
