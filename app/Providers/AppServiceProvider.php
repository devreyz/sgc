<?php

namespace App\Providers;

use App\Actions\Passkeys\GenerateSecureRegistrationOptions;
use App\Actions\Passkeys\StoreSecurePasskey;
use App\Actions\Passkeys\VerifySecurePasskey;
use App\Contracts\GoogleIdTokenVerifier;
use App\Models\AccessInvitation;
use App\Models\Asset;
use App\Models\AssociateLedger;
use App\Models\AssociateReceipt;
use App\Models\BillingAuthorization;
use App\Models\CashMovement;
use App\Models\CollectivePurchase;
use App\Models\Customer;
use App\Models\CustomerBillingReceipt;
use App\Models\DeliveryConferenceSheet;
use App\Models\DirectPurchase;
use App\Models\Expense;
use App\Models\FiscalProfile;
use App\Models\Organization;
use App\Models\Passkey;
use App\Models\Product;
use App\Models\ProductionDelivery;
use App\Models\ProviderPaymentRequest;
use App\Models\PurchaseOrder;
use App\Models\Revenue;
use App\Models\SalesProject;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderPayment;
use App\Models\ServiceProvider as ServiceProviderModel;
use App\Models\ServiceProviderLedger;
use App\Observers\AssociateLedgerObserver;
use App\Observers\AssociateReceiptObserver;
use App\Observers\CashMovementObserver;
use App\Observers\CustomerBillingReceiptObserver;
use App\Observers\CustomerObserver;
use App\Observers\ExpenseObserver;
use App\Observers\OrganizationObserver;
use App\Observers\ProductionDeliveryObserver;
use App\Observers\ProductObserver;
use App\Observers\PurchaseOrderObserver;
use App\Observers\ServiceOrderObserver;
use App\Observers\ServiceProviderLedgerObserver;
use App\Observers\ServiceProviderObserver;
use App\Observers\TenantStoredFileObserver;
use App\Policies\AccessInvitationPolicy;
use App\Policies\BillingAuthorizationPolicy;
use App\Policies\DeliveryConferenceSheetPolicy;
use App\Policies\FiscalProfilePolicy;
use App\Policies\PasskeyPolicy;
use App\Services\CustomerHierarchyService;
use App\Services\GoogleApiIdTokenVerifier;
use App\Services\TenantIdentityService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Vite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Laravel\Passkeys\Actions\GenerateRegistrationOptions;
use Laravel\Passkeys\Actions\StorePasskey;
use Laravel\Passkeys\Actions\VerifyPasskey;
use Laravel\Passkeys\Passkeys;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(TenantIdentityService::class);
        $this->app->scoped(CustomerHierarchyService::class);
        $this->app->bind(GoogleIdTokenVerifier::class, GoogleApiIdTokenVerifier::class);
        Passkeys::ignoreRoutes();
        Passkeys::usePasskeyModel(Passkey::class);
        $this->app->bind(GenerateRegistrationOptions::class, GenerateSecureRegistrationOptions::class);
        $this->app->bind(StorePasskey::class, StoreSecurePasskey::class);
        $this->app->bind(VerifyPasskey::class, VerifySecurePasskey::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Em producao, nunca aceite um public/hot copiado por engano do
        // ambiente local. Os assets devem vir exclusivamente do manifest.
        if (! app()->environment('local')) {
            app(Vite::class)->useHotFile(storage_path('framework/vite.hot'));
        }

        Schema::defaultStringLength(191);
        Gate::policy(AccessInvitation::class, AccessInvitationPolicy::class);
        Gate::policy(Passkey::class, PasskeyPolicy::class);
        Gate::policy(BillingAuthorization::class, BillingAuthorizationPolicy::class);
        Gate::policy(DeliveryConferenceSheet::class, DeliveryConferenceSheetPolicy::class);
        Gate::policy(FiscalProfile::class, FiscalProfilePolicy::class);

        RateLimiter::for('passkey-options', function (Request $request): array {
            $limit = (int) config('security.rates.webauthn_per_minute', 10);

            return [
                Limit::perMinute($limit)->by('passkey-options-ip|'.$request->ip()),
                Limit::perMinute($limit)->by('passkey-options-session|'.$request->session()->getId()),
            ];
        });

        RateLimiter::for('passkey-verify', function (Request $request): array {
            $limit = (int) config('security.rates.webauthn_per_minute', 10);
            $credentialHash = hash('sha256', (string) $request->input('credential.id'));

            return [
                Limit::perMinute($limit)->by('passkey-verify-ip|'.$request->ip()),
                Limit::perMinute($limit)->by(
                    'passkey-verify-session|'.$request->session()->getId().'|'.$credentialHash
                ),
            ];
        });

        RateLimiter::for('invitation-token', fn (Request $request) => Limit::perHour(
            (int) config('security.rates.invitation_token_per_hour', 10)
        )->by('token|'.$request->ip()));

        RateLimiter::for('invitation-code', function (Request $request): array {
            $limit = (int) config('security.rates.invitation_code_per_hour', 10);
            $invitation = (string) $request->session()->get('access_invitation_id', 'unknown');

            return [
                Limit::perHour($limit)->by('code-invite|'.$invitation),
                Limit::perHour($limit)->by('code-session|'.$request->session()->getId()),
                Limit::perHour($limit)->by('code-ip|'.$request->ip()),
            ];
        });

        RateLimiter::for('invitation-create', fn (Request $request) => Limit::perHour(
            (int) config('security.rates.invitation_create_per_hour', 20)
        )->by('create|'.$request->user()?->id.'|'.session('tenant_id')));

        RateLimiter::for('invitation-send', fn (Request $request) => Limit::perHour(
            (int) config('security.rates.invitation_send_per_hour', 20)
        )->by('send|'.$request->user()?->id.'|'.session('tenant_id').'|'.$request->route('associate')));

        RateLimiter::for('google-callback', fn (Request $request) => Limit::perMinute(
            (int) config('security.rates.google_callback_per_minute', 10)
        )->by('google|'.$request->session()->getId().'|'.$request->ip()));

        RateLimiter::for('google-native', function (Request $request): array {
            $limit = (int) config('security.rates.google_native_per_minute', 10);

            return [
                Limit::perMinute($limit)->by('google-native-ip|'.$request->ip()),
                Limit::perMinute($limit)->by('google-native-session|'.$request->session()->getId()),
            ];
        });

        RateLimiter::for('auth-state', fn (Request $request) => Limit::perMinute(30)
            ->by('auth-state|'.$request->session()->getId().'|'.$request->ip()));

        RateLimiter::for('google-drive-oauth', fn (Request $request) => Limit::perHour(10)
            ->by('google-drive|'.$request->user()?->id.'|'.$request->session()->getId().'|'.$request->ip()));

        // Grant all permissions to super_admin
        // This allows super admin to bypass all Gate and Policy checks
        Gate::before(function ($user, $ability) {
            if ($user->hasRole('super_admin')) {
                return true;
            }

            // A seguranca da conta e global. As policies dessas rotas ainda
            // exigem usuario ativo, vinculo ativo e propriedade do recurso.
            if (request()->routeIs('security.index', 'security.passkeys.*', 'security.reauth.*')) {
                return null;
            }

            // For non-super-admin users, enforce tenant check
            // Block access if no tenant is set (except for tenant selection routes)
            if (! request()->is('tenant/*') && ! request()->is('super-admin/*')) {
                $currentTenantId = session('tenant_id');
                if (! $currentTenantId && ! $user->isSuperAdmin()) {
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
        AssociateReceipt::observe(AssociateReceiptObserver::class);
        CustomerBillingReceipt::observe(CustomerBillingReceiptObserver::class);
        Customer::observe(CustomerObserver::class);
        Organization::observe(OrganizationObserver::class);
        Asset::observe(TenantStoredFileObserver::class);
        CollectivePurchase::observe(TenantStoredFileObserver::class);
        DirectPurchase::observe(TenantStoredFileObserver::class);
        Expense::observe(TenantStoredFileObserver::class);
        ProviderPaymentRequest::observe(TenantStoredFileObserver::class);
        Revenue::observe(TenantStoredFileObserver::class);
        SalesProject::observe(TenantStoredFileObserver::class);
        ServiceOrder::observe(TenantStoredFileObserver::class);
        ServiceOrderPayment::observe(TenantStoredFileObserver::class);
    }
}
