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
                'primary' => Color::Amber,
            ])
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
