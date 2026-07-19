<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRecentAuthentication
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->recentlyAuthenticated()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Confirme sua identidade novamente para continuar.',
                    'reauth_url' => route('auth.google', ['intent' => 'reauth']),
                ], 423);
            }

            return redirect()->route('security.index')
                ->with('error', 'Confirme sua identidade novamente para continuar.');
        }

        return $next($request);
    }
}
