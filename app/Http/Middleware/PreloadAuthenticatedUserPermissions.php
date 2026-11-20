<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreloadAuthenticatedUserPermissions
{
    /**
     * Handle an incoming request.
     * Refresh authorization relations at the request boundary so an existing
     * authenticated session observes role and direct-permission revocations.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($user = $request->user()) {
            $user->unsetRelation('roles');
            $user->unsetRelation('permissions');
            $user->load(['roles.permissions', 'permissions']);
        }

        return $next($request);
    }
}
