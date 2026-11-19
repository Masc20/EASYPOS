<?php

namespace App\Http\Middleware;

use App\Support\Constants\SystemConstants;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureTerminalUnlocked
{
    /**
     * Enforce persistent server-side terminal lock across reloads, navigation, and Livewire calls.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::guard('web')->check() || ! $request->hasSession()) {
            return $next($request);
        }

        if (! $request->session()->get(SystemConstants::SESSION_TERMINAL_LOCKED, false)) {
            return $next($request);
        }

        if (
            $request->routeIs('terminal.lock.show', 'terminal.lock', 'terminal.unlock', 'logout', 'filament.admin.auth.logout')
            || $request->is('terminal/lock', 'terminal/unlock', 'logout', 'admin/logout', 'up')
        ) {
            return $next($request);
        }

        if ($request->expectsJson() && ! $request->hasHeader('X-Livewire')) {
            return response()->json([
                'message' => 'Terminal is locked.',
                'locked' => true,
                'redirect' => route('terminal.lock.show'),
            ], 423);
        }

        return redirect()->route('terminal.lock.show');
    }
}
