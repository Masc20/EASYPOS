<?php

namespace App\Http\Middleware;

use App\Support\Constants\SystemConstants;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    /**
     * Immediately revoke sessions and log out authenticated users whose accounts were deactivated.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('web')->user();

        if (! $user) {
            return $next($request);
        }

        $isBranchInactive = method_exists($user, 'hasInactiveBranch') && $user->hasInactiveBranch();

        if ($user->is_active && ! $isBranchInactive) {
            return $next($request);
        }

        if ($request->routeIs('logout') || $request->is('logout')) {
            return $next($request);
        }

        $isFloorContext = ($request->hasSession() && $request->session()->get(SystemConstants::SESSION_AUTH_METHOD) === 'pin')
            || $user->isStrictFloorStaff();
        $isAdminContext = $request->is('admin*') && ! $isFloorContext;

        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        if ($request->routeIs('filament.admin.auth.logout') || $request->is('admin/logout')) {
            return redirect('/admin/login');
        }

        if ($request->routeIs('login', 'filament.admin.auth.login') || $request->is('login', 'admin/login')) {
            return $next($request);
        }

        $errorMessage = $isBranchInactive
            ? 'Your assigned branch is currently inactive. Please contact the system administrator.'
            : 'Your account has been deactivated. Please contact your manager or system administrator.';

        if ($request->expectsJson() && ! $request->hasHeader('X-Livewire')) {
            return response()->json([
                'message' => $errorMessage,
            ], 401);
        }

        $redirectUrl = $isAdminContext ? url('/admin/login') : route('login');

        return redirect()->to($redirectUrl)->withErrors([
            'emp_id' => $errorMessage,
        ]);
    }
}
