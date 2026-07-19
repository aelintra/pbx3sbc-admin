<?php

namespace App\Providers\Filament;

use Filament\Enums\ThemeMode;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use App\Filament\Pages\Dashboard;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Illuminate\Support\Facades\Blade;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->profile(isSimple: false)
            // Match pbx3spa shell: slate surfaces + blue #2563eb accent (STYLESYNC / SPA --pbx-*)
            ->brandName('PBX3 SBC')
            ->colors([
                'primary' => '#2563eb',
                'danger' => '#dc2626',
                'gray' => Color::Slate,
            ])
            ->defaultThemeMode(ThemeMode::Light)
            // Cache-bust: browsers cache theme.css hard without a query string (forms/support use ?v=).
            ->theme(asset('css/filament/admin/theme.css') . '?v=' . (string) filemtime(public_path('css/filament/admin/theme.css')))
            ->sidebarWidth('15.75rem') // match pbx3spa --pbx-shell-sidebar-width
            // SPA: brand in main topbar-left (not sidebar). Hide sidebar logo via theme.css.
            ->renderHook(
                PanelsRenderHook::TOPBAR_START,
                fn (): string => Blade::render('@include(\'filament.hooks.topbar-brand\')'),
            )
            // SPA: inline Logged in as + Logout (hide Filament avatar menu via theme.css).
            ->renderHook(
                PanelsRenderHook::TOPBAR_END,
                fn (): string => Blade::render('@include(\'filament.hooks.topbar-user\')'),
            )
            // SPA: © Aelintra Telecom at bottom of left nav.
            ->renderHook(
                PanelsRenderHook::SIDEBAR_FOOTER,
                fn (): string => Blade::render('@include(\'filament.hooks.sidebar-footer\')'),
            )
            // Most-used ops first (Peering then Routing); Fail2Ban then Logs at the bottom.
            ->navigationGroups([
                NavigationGroup::make('Peering'),
                NavigationGroup::make('Routing'),
                NavigationGroup::make('Fail2Ban'),
                NavigationGroup::make('Logs'),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                // AccountWidget removed — SPA Home has no Welcome/Sign out card; Logout is in the topbar.
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
    }
}
