<?php

namespace App\Providers;

use App\Services\TenantResolver;
use Illuminate\Support\ServiceProvider;

class TenantServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(TenantResolver::class, function ($app) {
            return new TenantResolver();
        });

        // Bind tenant ID to container
        $this->app->bind('tenant.id', function ($app) {
            return $app->make(TenantResolver::class)->resolve();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
