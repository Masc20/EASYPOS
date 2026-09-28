<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
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
                \Filament\View\PanelsRenderHook::AUTH_LOGIN_FORM_AFTER,
                fn () => \Illuminate\Support\Facades\Blade::render('
                    <div class="mt-4 pt-4 border-t border-[#E8D8C5] dark:border-[#34383D] text-center text-xs text-[#72685E] dark:text-[#9BA3AF]">
                        <p>Floor Staff (Cashier, Cook, Chef)?</p>
                        <a href="{{ route(\'login\') }}" class="font-semibold text-[#5A0708] dark:text-[#F28C18] hover:underline inline-flex items-center gap-1 mt-1 transition">
                            &larr; Switch to Floor Staff Terminal (Emp ID + PIN)
                        </a>
                    </div>
                ')
            )
            ->pages([
                Dashboard::class,
            ])
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
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
     * Dynamically discover Filament resources, pages, clusters, and widgets across all domain slices.
     */
    protected function discoverDomainComponents(Panel $panel): Panel
    {
        // Root / shared Filament directories (if present)
        if (is_dir(app_path('Filament/Resources'))) {
            $panel->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources');
        }
        if (is_dir(app_path('Filament/Pages'))) {
            $panel->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages');
        }
        if (is_dir(app_path('Filament/Widgets'))) {
            $panel->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets');
        }
        if (is_dir(app_path('Filament/Clusters'))) {
            $panel->discoverClusters(in: app_path('Filament/Clusters'), for: 'App\\Filament\\Clusters');
        }

        // Modular Domain directories: app/Domains/{DomainName}/Filament/...
        $domainsPath = app_path('Domains');
        if (is_dir($domainsPath)) {
            $domainDirs = glob($domainsPath . '/*', GLOB_ONLYDIR) ?: [];

            foreach ($domainDirs as $domainDir) {
                $domainName = basename($domainDir);

                $resourcesPath = "{$domainDir}/Filament/Resources";
                if (is_dir($resourcesPath)) {
                    $panel->discoverResources(in: $resourcesPath, for: "App\\Domains\\{$domainName}\\Filament\\Resources");
                }

                $pagesPath = "{$domainDir}/Filament/Pages";
                if (is_dir($pagesPath)) {
                    $panel->discoverPages(in: $pagesPath, for: "App\\Domains\\{$domainName}\\Filament\\Pages");
                }

                $widgetsPath = "{$domainDir}/Filament/Widgets";
                if (is_dir($widgetsPath)) {
                    $panel->discoverWidgets(in: $widgetsPath, for: "App\\Domains\\{$domainName}\\Filament\\Widgets");
                }

                $clustersPath = "{$domainDir}/Filament/Clusters";
                if (is_dir($clustersPath)) {
                    $panel->discoverClusters(in: $clustersPath, for: "App\\Domains\\{$domainName}\\Filament\\Clusters");
                }
            }
        }

        return $panel;
    }
}
