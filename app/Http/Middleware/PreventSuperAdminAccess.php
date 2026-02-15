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
            // If the super admin does not belong to any tenant, keep redirecting
            // them to the super-admin panel. If they have any tenant memberships,
            // allow access to admin panels (they can act within those orgs).
            if (! Auth::user()->tenants()->exists()) {
                return redirect('/super-admin');
            }
            // otherwise allow access to the admin panel
        }

        return $next($request);
    }
}
