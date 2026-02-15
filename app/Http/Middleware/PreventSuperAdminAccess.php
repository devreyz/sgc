<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class PreventSuperAdminAccess
{
    /**
     * Handle an incoming request.
     *
     * Super admins should only access the super-admin panel, not the regular admin panel.
     * They are system administrators, not cooperative administrators.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check() && Auth::user()->isSuperAdmin()) {
            // Redirect super admin to their panel
            return redirect('/super-admin');
        }

        return $next($request);
    }
}
