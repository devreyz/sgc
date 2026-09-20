<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\OrganizationAuthorizedEmail;
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
        if (is_string($tenant)) {
            $tenant = Tenant::where('slug', $tenant)->first();

            if ($tenant) {
                $request->route()->setParameter('tenant', $tenant);
            }
        }

        if ($tenant instanceof Tenant) {
            $user = $request->user();
            $hasMembership = $user && TenantUser::query()
                ->where('tenant_id', $tenant->id)
                ->where('user_id', $user->id)
                ->where('status', true)
                ->exists();
            $hasBuyerAccess = $user
                && $request->routeIs('buyer.*')
                && OrganizationAuthorizedEmail::withoutGlobalScope('tenant')
                    ->where('tenant_id', $tenant->id)
                    ->whereRaw('LOWER(email) = ?', [mb_strtolower((string) $user->email)])
                    ->where('active', true)
                    ->whereHas('organization', fn ($query) => $query
                        ->withoutGlobalScope('tenant')
                        ->where('tenant_id', $tenant->id)
                        ->where('active', true))
                    ->exists();

            abort_unless($hasMembership || $hasBuyerAccess || $user?->hasRole('super_admin'), 403, 'Acesso não autorizado a esta organização.');

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
