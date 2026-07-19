<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withProviders([
        App\Providers\AppServiceProvider::class,
        App\Providers\Filament\AdminPanelProvider::class,
        App\Providers\Filament\SuperAdminPanelProvider::class,
        App\Providers\ShieldCustomizationServiceProvider::class,
    ])
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \App\Http\Middleware\CheckUserRole::class,
            'any.role' => \App\Http\Middleware\CheckAnyRole::class,
            'tenant.slug' => \App\Http\Middleware\TenantFromSlugMiddleware::class,
            'buyer.organization' => \App\Http\Middleware\EnsureBuyerOrganizationAccess::class,
            'auth.recent' => \App\Http\Middleware\EnsureRecentAuthentication::class,
            'webauthn.config' => \App\Http\Middleware\EnsureWebAuthnConfiguration::class,
            'invitation.headers' => \App\Http\Middleware\InvitationSecurityHeaders::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
