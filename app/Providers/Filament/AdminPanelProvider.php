<?php

namespace App\Providers\Filament;

use App\Http\Middleware\TenantMiddleware;
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
                        'lg' => 3,
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
                'PDV',
                'Sistema',
            ])
            ->sidebarCollapsibleOnDesktop()
            ->databaseNotifications()
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')
            ->globalSearchKeyBindings(['command+k', 'ctrl+k'])
            ->maxContentWidth('full')
            ->renderHook(
                'panels::global-search.before',
                fn (): string => Blade::render('
                    @php
                        $tenantSlug = session("tenant_slug");
                        $hubUrl = $tenantSlug ? "/" . $tenantSlug . "/delivery" : "/";
                    @endphp
                    <a href="{{ $hubUrl }}"
                       title="Voltar ao Hub"
                       style="display:inline-flex;align-items:center;gap:.35rem;padding:.35rem .7rem;border-radius:.5rem;background:rgba(16,185,129,.1);font-size:.8rem;font-weight:600;text-decoration:none;border:1px solid rgba(16,185,129,.25);transition:background .15s,border-color .15s;white-space:nowrap"
                       onmouseover="this.style.background=\'rgba(16,185,129,.18)\';this.style.borderColor=\'rgba(16,185,129,.5)\'"
                       onmouseout="this.style.background=\'rgba(16,185,129,.1)\';this.style.borderColor=\'rgba(16,185,129,.25)\'">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                        Voltar ao Hub
                    </a>
                ')
            )
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
