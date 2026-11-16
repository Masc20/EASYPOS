<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\Auth\Login;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $panel = $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->brandName('EASYPOS Admin')
            ->login(Login::class)
            ->darkMode(true)
            ->spa(hasPrefetching: true)
            ->colors([
                'primary' => [
                    50 => '#FBF3F3',
                    100 => '#F6E4E5',
                    200 => '#EDC4C6',
                    300 => '#DE9A9D',
                    400 => '#960D10',
                    500 => '#7A0B0D',
                    600 => '#5A0708',
                    700 => '#450506',
                    800 => '#320404',
                    900 => '#210203',
                    950 => '#140102',
                ],
                'secondary' => Color::hex('#F28C18'),
                'warning' => Color::hex('#F6C744'),
                'success' => Color::hex('#16833D'),
                'danger' => Color::hex('#C62828'),
                'gray' => [
                    50 => '#F8F3EA',
                    100 => '#F3ECE0',
                    200 => '#E8D8C5',
                    300 => '#DAC6AE',
                    400 => '#9E9182',
                    500 => '#72685E',
                    600 => '#4E473F',
                    700 => '#34383D',
                    800 => '#22262B',
                    900 => '#191C20',
                    950 => '#111315',
                ],
            ])
            ->font(
                family: 'ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif',
                provider: \Filament\FontProviders\LocalFontProvider::class,
            )
            ->renderHook(
                \Filament\View\PanelsRenderHook::HEAD_END,
                fn () => app(\Illuminate\Foundation\Vite::class)(['resources/css/app.css'])
            )
            ->pages([
                Dashboard::class,
            ])
            ->widgets([
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                \App\Http\Middleware\SecurityHeaders::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                \App\Http\Middleware\PreloadAuthenticatedUserPermissions::class,
            ]);

        // Discover components across modular domains
        return $this->discoverDomainComponents($panel);
    }

    /**
     * Discover Filament resources, pages, and widgets without redundant domain directory globbing.
     */
    protected function discoverDomainComponents(Panel $panel): Panel
    {
        $panel
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets');

        return $panel;
    }
}
