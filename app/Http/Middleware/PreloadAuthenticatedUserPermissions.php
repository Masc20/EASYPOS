<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreloadAuthenticatedUserPermissions
{
    /**
     * Handle an incoming request.
     * Eagerly loads roles and permissions for authenticated users to eliminate
     * lazy-loading query cascades during authorization and Blade view rendering.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($user = $request->user()) {
            $user->loadMissing(['roles', 'permissions']);
        }

        return $next($request);
    }
}
