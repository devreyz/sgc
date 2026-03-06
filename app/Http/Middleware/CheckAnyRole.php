<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckAnyRole
{
    /**
     * Handle an incoming request.
     * Verifica se o usuário tem QUALQUER um dos roles especificados.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$roles Lista de roles aceitos
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        if (!$request->user()) {
            return redirect('/login')->with('error', 'Você precisa estar autenticado.');
        }

        $user = $request->user();

        // Super admins e admins globais (Spatie) passam sempre
        if ($user->hasAnyRole(['super_admin', 'admin'])) {
            return $next($request);
        }

        // Verifica roles globais (Spatie)
        if ($user->hasAnyRole($roles)) {
            return $next($request);
        }

        // Verifica roles no tenant atual (pivot)
        if ($user->hasRoleInTenant($roles)) {
            return $next($request);
        }

        return redirect('/')->with('error', 'Você não tem permissão para acessar esta área.');
    }
}
