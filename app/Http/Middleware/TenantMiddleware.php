<?php

namespace App\Http\Middleware;

use App\Services\TenantResolver;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class TenantMiddleware
{
    protected TenantResolver $tenantResolver;

    public function __construct(TenantResolver $tenantResolver)
    {
        $this->tenantResolver = $tenantResolver;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip for guest users
        if (!Auth::check()) {
            return $next($request);
        }

        $user = Auth::user();

        // Super admin can access without tenant
        if ($user->isSuperAdmin()) {
            // Try to resolve tenant if set in session
            $tenantId = $this->tenantResolver->resolve();
            
            if ($tenantId) {
                $tenant = $this->tenantResolver->current();
                View::share('currentTenant', $tenant);
                app()->instance('currentTenant', $tenant);
            }
            
            return $next($request);
        }

        // Auto-select tenant for regular users
        $tenantId = $this->tenantResolver->autoSelectTenant();

        // Block access if no tenant available
        if (!$tenantId) {
            // Check if user has any tenants
            if (!$user->hasAnyTenant()) {
                return response()->view('errors.no-tenant', [
                    'message' => 'Você não está vinculado a nenhuma organização. Entre em contato com o administrador.',
                ], 403);
            }

            // User has multiple tenants but hasn't selected one
            return redirect()->route('tenant.select');
        }

        // Validate tenant access
        if (!$this->tenantResolver->userHasAccess($user, $tenantId)) {
            // Clear invalid tenant
            $this->tenantResolver->clearTenant();
            
            return response()->view('errors.no-tenant', [
                'message' => 'Você não tem acesso a esta organização.',
            ], 403);
        }

        // Share current tenant with views
        $tenant = $this->tenantResolver->current();
        View::share('currentTenant', $tenant);
        
        // Register tenant in container for easy access
        app()->instance('currentTenant', $tenant);

        return $next($request);
    }
}
