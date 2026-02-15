<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use App\Http\Middleware\TenantMiddleware;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->colors([
                'primary' => Color::Emerald,
                'danger' => Color::Rose,
                'gray' => Color::Slate,
                'info' => Color::Blue,
                'success' => Color::Green,
                'warning' => Color::Amber,
            ])
            ->font('Inter')
            ->brandName('SGC - Sistema de Gestão de Cooperativa')
            ->brandLogo(fn () => view('filament.logo'))
            ->favicon(asset('favicon.ico'))
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                \App\Filament\Widgets\TenantSelectorWidget::class,
                \App\Filament\Widgets\PendingPaymentRequestsWidget::class,
                \App\Filament\Widgets\ServiceOrdersPaymentsWidget::class,
                \App\Filament\Widgets\AssociatesBalanceWidget::class,
                \App\Filament\Widgets\CashSummaryWidget::class,
                \App\Filament\Widgets\ProjectsProgressWidget::class,
                \App\Filament\Widgets\LowStockWidget::class,
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
                TenantMiddleware::class,
                \App\Http\Middleware\PreventSuperAdminAccess::class,
            ])
            ->plugins([
                \BezhanSalleh\FilamentShield\FilamentShieldPlugin::make()
                    ->gridColumns([
                        'default' => 1,
                        'sm' => 2,
                        'lg' => 3
                    ])
                    ->sectionColumnSpan(1)
                    ->checkboxListColumns([
                        'default' => 1,
                        'sm' => 2,
                        'lg' => 4,
                    ])
                    ->resourceCheckboxListColumns([
                        'default' => 1,
                        'sm' => 2,
                    ]),
            ])
            ->navigationGroups([
                'Cadastros',
                'Financeiro',
                'Projetos de Venda',
                'Compras Coletivas',
                'Serviços',
                'Estoque',
                'Sistema',
            ])
            ->sidebarCollapsibleOnDesktop()
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')
            ->globalSearchKeyBindings(['command+k', 'ctrl+k'])
            ->maxContentWidth('full')
            ->renderHook(
                'panels::head.end',
                fn (): string => Blade::render('
                    <link rel="manifest" href="/manifest.json">
                    <meta name="theme-color" content="#2563eb">
                    <meta name="apple-mobile-web-app-capable" content="yes">
                    <meta name="apple-mobile-web-app-status-bar-style" content="default">
                    <meta name="apple-mobile-web-app-title" content="SGC">
                    <link rel="apple-touch-icon" href="/icons/icon-192x192.png">
                    <style>
                        /* Grid paper background for Filament panel */
                        body::before {
                            content: "";
                            position: fixed;
                            top: 0;
                            left: 0;
                            width: 100%;
                            height: 100%;
                            background-image: 
                                linear-gradient(rgba(37, 99, 235, 0.03) 1px, transparent 1px),
                                linear-gradient(90deg, rgba(37, 99, 235, 0.03) 1px, transparent 1px);
                            background-size: 20px 20px;
                            z-index: 0;
                            pointer-events: none;
                        }

                        /* Ensure Filament UI sits above the background */
                        .filament-panels, .filament-app, .filament-main {
                            position: relative;
                            z-index: 1;
                        }

                        /* Discrete, theme-aware scrollbar for Filament sidebar */
                        .fi-sidebar-nav {
                            overflow-y: auto;
                            scrollbar-width: thin;
                            scrollbar-color: rgba(17,24,39,0.12) transparent; /* firefox */
                        }

                        .fi-sidebar-nav::-webkit-scrollbar {
                            width: 8px;
                        }

                        .fi-sidebar-nav::-webkit-scrollbar-track {
                            background: transparent;
                        }

                        .fi-sidebar-nav::-webkit-scrollbar-thumb {
                            background-color: rgba(17,24,39,0.12);
                            border-radius: 8px;
                            border: 2px solid transparent;
                            background-clip: padding-box;
                        }

                        .fi-sidebar-nav:hover::-webkit-scrollbar-thumb {
                            background-color: rgba(17,24,39,0.18);
                        }

                        /* Dark theme adjustments: prefer explicit dark class and prefers-color-scheme */
                        .dark .fi-sidebar-nav,
                        [data-theme="dark"] .fi-sidebar-nav,
                        @media (prefers-color-scheme: dark) {
                            .fi-sidebar-nav {
                                scrollbar-color: rgba(255,255,255,0.08) transparent;
                            }
                            .fi-sidebar-nav::-webkit-scrollbar-thumb {
                                background-color: rgba(255,255,255,0.08);
                            }
                            .fi-sidebar-nav:hover::-webkit-scrollbar-thumb {
                                background-color: rgba(255,255,255,0.12);
                            }
                        }
                    </style>
                ')
            )
            ->renderHook(
                'panels::body.end',
                fn (): string => Blade::render('
                    <script>
                        if ("serviceWorker" in navigator) {
                            window.addEventListener("load", function() {
                                navigator.serviceWorker.register("/sw.js")
                                    .then(function(registration) {
                                        console.log("ServiceWorker registered:", registration.scope);
                                    })
                                    .catch(function(error) {
                                        console.log("ServiceWorker registration failed:", error);
                                    });
                            });
                        }
                    </script>
                ')
            );
    }
}
