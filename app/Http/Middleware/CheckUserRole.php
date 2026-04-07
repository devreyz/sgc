<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckUserRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param string $role The required role (service_provider or associate)
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        if (!$request->user()) {
            return redirect('/login')->with('error', 'Você precisa estar autenticado.');
        }

        $user = $request->user();

        // Super admins global passam sempre
        if ($user->hasAnyRole(['super_admin', 'admin'])) {
            return $next($request);
        }

        // Verifica role global (Spatie)
        if ($user->hasRole($role)) {
            return $next($request);
        }

        // Verifica role no tenant atual (pivot)
        $routeTenant = $request->route('tenant');
        $tenantId = ($routeTenant instanceof \App\Models\Tenant)
            ? $routeTenant->id
            : session('tenant_id');

        if ($user->hasRoleInTenant($role, $tenantId)) {
            return $next($request);
        }

        return redirect('/')->with('error', 'Você não tem permissão para acessar esta área.');
    }
}
