<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(
            at: ['10.0.0.0/8', '172.16.0.0/12', '172.31.0.0/16', '192.168.0.0/16'],
            headers: Request::HEADER_X_FORWARDED_AWS_ELB,
        );

        $middleware->web(append: [
            \App\Http\Middleware\SecurityHeaders::class,
            \App\Http\Middleware\EnsureUserIsActive::class,
            \App\Http\Middleware\EnsureTerminalUnlocked::class,
            \App\Http\Middleware\PreloadAuthenticatedUserPermissions::class,
        ]);
        $middleware->alias([
            'active' => \App\Http\Middleware\EnsureUserIsActive::class,
            'terminal.unlocked' => \App\Http\Middleware\EnsureTerminalUnlocked::class,
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);

        $middleware->prependToPriorityList(
            before: \Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class,
            prepend: \App\Http\Middleware\EnsureUserIsActive::class,
        );
        $middleware->prependToPriorityList(
            before: \Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class,
            prepend: \App\Http\Middleware\EnsureTerminalUnlocked::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Throwable $e, Request $request) {
            if (class_exists(\Livewire\Features\SupportRedirects\SupportRedirects::class)) {
                while (! empty(\Livewire\Features\SupportRedirects\SupportRedirects::$redirectorCacheStack)) {
                    $previousRedirector = array_pop(\Livewire\Features\SupportRedirects\SupportRedirects::$redirectorCacheStack);
                    if ($previousRedirector instanceof \Illuminate\Routing\Redirector) {
                        app()->instance('redirect', $previousRedirector);
                    }
                }
            }

            $isCsrf419 = $e instanceof \Illuminate\Session\TokenMismatchException
                || ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface && $e->getStatusCode() === 419);

            if ($isCsrf419 && ($request->is('logout') || $request->is('admin/logout'))) {
                $isAdminLogout = $request->is('admin/logout');
                $user = \Illuminate\Support\Facades\Auth::guard('web')->user();
                $isFloorStaff = $request->hasSession()
                    ? ($request->session()->get(\App\Support\Constants\SystemConstants::SESSION_AUTH_METHOD) === 'pin'
                        || ($user && $user->isStrictFloorStaff()))
                    : ! $isAdminLogout;

                if ($user) {
                    \Illuminate\Support\Facades\Auth::guard('web')->logout();
                    event(new \App\Domains\Identity\Events\UserLoggedOut($user));
                }

                if ($request->hasSession()) {
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();
                }

                return ($isAdminLogout || ($user && ! $isFloorStaff))
                    ? redirect('/admin/login')
                    : redirect()->route('login');
            }

            $isForbidden403 = $e instanceof \Illuminate\Auth\Access\AuthorizationException
                || $e instanceof \Spatie\Permission\Exceptions\UnauthorizedException
                || ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface && $e->getStatusCode() === 403);

            if ($isForbidden403) {
                $deniedUser = \Illuminate\Support\Facades\Auth::guard('web')->user();
                \App\Domains\Audit\Services\ActivityLogger::denied(
                    category: 'security',
                    event: 'access_denied',
                    description: 'Denied (403) access to ' . $request->method() . ' /' . ltrim($request->path(), '/') . ': ' . ($e->getMessage() ?: 'Forbidden'),
                    actor: $deniedUser,
                    branchId: $deniedUser?->branch_id,
                    metadata: ['path' => $request->path(), 'method' => $request->method(), 'status_code' => 403],
                );
            }

            if (! (app()->environment('production') || config('app.env') === 'production')) {
                return null;
            }

            if ($e instanceof \Illuminate\Validation\ValidationException ||
                $e instanceof \Illuminate\Auth\AuthenticationException ||
                ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface && $e->getStatusCode() < 500)) {
                return null;
            }

            \Illuminate\Support\Facades\Log::error($e->getMessage(), ['exception' => $e]);

            if ($request->expectsJson() || $request->hasHeader('X-Livewire')) {
                return response()->json([
                    'message' => 'An unexpected Server Error occurred.',
                ], 500);
            }

            if (view()->exists('errors.500')) {
                return response()->view('errors.500', [], 500);
            }

            return response('Server Error', 500);
        });
    })->create();
