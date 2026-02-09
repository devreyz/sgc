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

        if (!$request->user()->hasRole($role)) {
            return redirect('/')->with('error', 'Você não tem permissão para acessar esta área.');
        }

        return $next($request);
    }
}
