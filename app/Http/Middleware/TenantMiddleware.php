<?php

namespace App\Http\Middleware;

use App\Services\TenantResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TenantMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $resolver = app(TenantResolver::class);
        $tenantId = $resolver->resolve();

        // Register tenant in container for easy access
        if ($tenantId) {
            app()->instance('tenant.id', $tenantId);
        }

        // CRITICAL: Require tenant selection for ALL users (including super admin)
        // when accessing admin panel or resources
        if (!$tenantId && !$this->isExemptRoute($request)) {
            // Allow super admin to access tenant selection and management
            if (auth()->check() && (auth()->user()->is_super_admin ?? false)) {
                // If accessing admin panel without tenant, redirect to tenant selector
                if ($request->is('admin') || $request->is('admin/*')) {
                    return redirect()->route('filament.admin.pages.tenant-selector');
                }
            }

            // For regular users, require tenant
            if (auth()->check()) {
                $userTenants = auth()->user()->tenants;
                
                if ($userTenants->count() > 1) {
                    return redirect()->route('tenant.select');
                } elseif ($userTenants->count() === 0) {
                    abort(403, 'Você não pertence a nenhuma organização. Contate o administrador.');
                }
            }

            abort(403, 'Nenhuma organização ativa. Por favor, selecione uma organização.');
        }

        return $next($request);
    }

    /**
     * Routes that don't require tenant
     */
    protected function isExemptRoute(Request $request): bool
    {
        $exemptRoutes = [
            'home',
            'login',
            'logout',
            'auth.google',
            'auth.google.callback',
            'tenant.select',
            'tenant.switch',
            'tenant.switch.get',
            'tenant.current',
            'tenant.clear',
            'password.*',
            'filament.auth.*',
            'filament.admin.pages.tenant-selector',
        ];

        foreach ($exemptRoutes as $pattern) {
            if ($request->routeIs($pattern)) {
                return true;
            }
        }

        return false;
    }
}
