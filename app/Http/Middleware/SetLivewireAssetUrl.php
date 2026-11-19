<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Livewire\Mechanisms\FrontendAssets\FrontendAssets;
use Symfony\Component\HttpFoundation\Response;

class SetLivewireAssetUrl
{
    public function handle(Request $request, Closure $next): Response
    {
        $base = $request->getBaseUrl();
        if ($base === '' || config('livewire.asset_url') || is_file(public_path('vendor/livewire/manifest.json'))) {
            return $next($request);
        }

        $script = app(FrontendAssets::class)->javaScriptRoute->uri();
        config(['livewire.asset_url' => $base.'/'.ltrim($script, '/')]);
        // Filament's Livewire script is injected after the middleware pipeline returns.
        app()->terminating(fn () => config(['livewire.asset_url' => null]));

        return $next($request);
    }
}
