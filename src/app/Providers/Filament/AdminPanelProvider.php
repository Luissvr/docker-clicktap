<?php

namespace App\Providers\Filament;

use App\Filament\Pages\ConfiguracionPrecios;
use App\Filament\Pages\Contabilidad;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\PublicacionesSitio;
use App\Filament\Pages\StockTarjetas;
use App\Filament\Pages\Ventas;
use App\Filament\Resources\ClienteResource\Pages\ListClientes;
use App\Filament\Resources\PaginaResource\Pages\ListPaginas;
use App\Support\CatalogConnection;
use Filament\Enums\ThemeMode;
use Filament\FontProviders\LocalFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Tables\View\TablesRenderHook;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->brandName('ClickTap')
            ->brandLogo(fn () => auth()->check()
                ? view('filament.components.brand-logo')
                : asset('images/clicktap-horizontal.png'))
            ->brandLogoHeight('34px')
            ->login()
            ->spa()
            ->databaseTransactions()
            ->bootUsing(fn () => app()->setLocale('es'))
            ->darkMode(false)
            ->defaultThemeMode(ThemeMode::Light)
            ->breadcrumbs(false)
            ->globalSearch(false)
            ->sidebarWidth('186px')
            ->collapsedSidebarWidth('72px')
            ->sidebarCollapsibleOnDesktop()
            ->collapsibleNavigationGroups(false)
            ->font('Inter', url: asset('build/admin/fonts.css'), provider: LocalFontProvider::class)
            ->theme(asset('build/admin/theme.css').'?v='.(file_exists(public_path('build/admin/theme.css')) ? filemtime(public_path('build/admin/theme.css')) : time()))
            ->colors([
                'primary' => '#B6FF00',
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->pages([
                Dashboard::class,
                Ventas::class,
                StockTarjetas::class,
                Contabilidad::class,
                PublicacionesSitio::class,
                ConfiguracionPrecios::class,
            ])
            ->renderHook(
                PanelsRenderHook::TOPBAR_START,
                fn () => view('filament.components.header-title')
            )
            ->renderHook(
                PanelsRenderHook::PAGE_START,
                fn () => view('filament.components.page-title')
            )
            ->renderHook(
                TablesRenderHook::TOOLBAR_START,
                fn () => view('filament.components.clientes-toolbar'),
                scopes: ListClientes::class
            )
            ->renderHook(
                TablesRenderHook::TOOLBAR_START,
                fn () => view('filament.components.paginas-toolbar'),
                scopes: ListPaginas::class
            )
            ->renderHook(
                PanelsRenderHook::SIDEBAR_NAV_START,
                fn (): string => CatalogConnection::enabled() && auth()->check()
                    ? Blade::render('@livewire("catalog-sync-status")')
                    : ''
            )
            ->renderHook(
                PanelsRenderHook::SIDEBAR_FOOTER,
                fn (): string => auth()->check()
                    ? Blade::render('@include("filament.components.sidebar-user-profile")')
                    : ''
            )
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
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
    }
}
