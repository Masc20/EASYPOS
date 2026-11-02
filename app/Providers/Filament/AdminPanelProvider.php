<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use App\Filament\Pages\Dashboard;
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
            ->login()
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
            ->renderHook(
                \Filament\View\PanelsRenderHook::AUTH_LOGIN_FORM_AFTER,
                fn () => \Illuminate\Support\Facades\Blade::render('
                    <div class="mt-4 pt-4 border-t border-[#E8D8C5] dark:border-[#34383D] text-xs">
                        <div class="mt-3 text-center text-[#72685E] dark:text-[#9BA3AF]">
                            <p>Floor Staff (Cashier, Cook, Chef)?</p>
                            <a href="{{ route(\'login\') }}" class="font-semibold text-[#5A0708] dark:text-[#F28C18] hover:underline inline-flex items-center gap-1 mt-1 transition">
                                &larr; Switch to Floor Staff Terminal (Emp ID + PIN)
                            </a>
                        </div>
                    </div>
                ')
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
            ])
            ->authMiddleware([
                Authenticate::class,
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
