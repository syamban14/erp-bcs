<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use App\Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Navigation\NavigationItem;
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
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->brandName('BCS Logistics')
            ->brandLogo('/resources/MYBCS.png')
            ->brandLogoHeight('5rem')
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->navigationItems([
                NavigationItem::make('Native Logs (Opcodes)')
                    ->url('/log-viewer', shouldOpenInNewTab: true)
                    ->icon('heroicon-o-document-magnifying-glass')
                    ->group('System Monitor')
                    ->sort(101)
                    ->visible(fn (): bool => auth()->check() && strtolower(auth()->user()->role) === 'superhyperadmin'),
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                // Custom dashboard widgets will be auto-discovered
            ])
            ->maxContentWidth('full')
            ->renderHook(
                \Filament\View\PanelsRenderHook::BODY_START,
                fn(): string => '<style>
                    /* ── Sidebar logo (dashboard) ── */
                    .fi-sidebar-header { justify-content: center !important; }
                    .fi-sidebar .fi-logo { display: flex; justify-content: center; width: 100%; }
                    .fi-sidebar .fi-logo img { object-fit: contain; }

                    /* ── Login page logo — lebih kecil & proporsional ── */
                    .fi-simple-layout .fi-logo,
                    .fi-auth-card .fi-logo {
                        display: flex;
                        justify-content: center;
                        align-items: center;
                        margin-bottom: 0.5rem;
                    }
                    .fi-simple-layout .fi-logo img,
                    .fi-auth-card .fi-logo img {
                        height: 3rem !important;
                        max-width: 180px;
                        object-fit: contain;
                    }
                </style>',
            )
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
