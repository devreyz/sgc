<?php

namespace App\Providers;

use App\Models\AssociateLedger;
use App\Models\CashMovement;
use App\Models\Expense;
use App\Models\Product;
use App\Models\ProductionDelivery;
use App\Models\PurchaseOrder;
use App\Models\ServiceOrder;
use App\Models\ServiceProvider as ServiceProviderModel;
use App\Models\ServiceProviderLedger;
use App\Observers\AssociateLedgerObserver;
use App\Observers\CashMovementObserver;
use App\Observers\ExpenseObserver;
use App\Observers\ProductionDeliveryObserver;
use App\Observers\ProductObserver;
use App\Observers\PurchaseOrderObserver;
use App\Observers\ServiceOrderObserver;
use App\Observers\ServiceProviderObserver;
use App\Observers\ServiceProviderLedgerObserver;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Gate;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);

        // Grant all permissions to super_admin
        // This allows super admin to bypass all Gate and Policy checks
        Gate::before(function ($user, $ability) {
            if ($user->hasRole('super_admin')) {
                return true;
            }
            
            // For non-super-admin users, enforce tenant check
            // Block access if no tenant is set (except for tenant selection routes)
            if (!request()->is('tenant/*') && !request()->is('super-admin/*')) {
                $currentTenantId = session('tenant_id');
                if (!$currentTenantId && !$user->isSuperAdmin()) {
                    return false;
                }
            }
            
            return null;
        });

        // Register Observers
        CashMovement::observe(CashMovementObserver::class);
        ProductionDelivery::observe(ProductionDeliveryObserver::class);
        PurchaseOrder::observe(PurchaseOrderObserver::class);
        ServiceOrder::observe(ServiceOrderObserver::class);
        Expense::observe(ExpenseObserver::class);
        Product::observe(ProductObserver::class);
        AssociateLedger::observe(AssociateLedgerObserver::class);
        ServiceProviderModel::observe(ServiceProviderObserver::class);
        ServiceProviderLedger::observe(ServiceProviderLedgerObserver::class);
    }
}
