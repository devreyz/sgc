<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware que resolve o tenant pelo slug na URL e define na sessão
 * Usado para rotas públicas/legadas com prefixo {tenant:slug}
 */
class TenantFromSlugMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Pegar tenant da rota (route parameter binding automático)
        $tenant = $request->route('tenant');

        if ($tenant instanceof Tenant) {
            // Definir tenant_id na sessão
            session(['tenant_id' => $tenant->id, 'tenant_slug' => $tenant->slug]);

            // Compartilhar tenant com views (sempre define a variável)
            view()->share('currentTenant', $tenant ?? null);

            // Definir locale se tenant tiver configuração
            if ($tenant->locale) {
                app()->setLocale($tenant->locale);
            }
        } else {
            // Garantir que a variável exista nas views mesmo quando não houver tenant na rota
            view()->share('currentTenant', null);
        }

        return $next($request);
    }
}
