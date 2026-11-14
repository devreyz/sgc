<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PublicPageSecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $styleNonce = null;
        if ($request->routeIs('home') && ! $request->user()) {
            $styleNonce = base64_encode(random_bytes(16));
            $request->attributes->set('welcome_style_nonce', $styleNonce);
        }

        $response = $next($request);

        // A rota raiz também entrega o Hub autenticado, cujo layout possui estilos
        // inline legados. A CSP pública só deve ser aplicada à landing page.
        if (! $request->user()) {
            $styleSources = "'self' https://fonts.bunny.net https://cdn.jsdelivr.net";
            if ($styleNonce !== null) {
                $styleSources .= " 'nonce-{$styleNonce}'";
            }

            $response->headers->set(
                'Content-Security-Policy',
                "default-src 'self'; script-src 'self'; style-src {$styleSources}; img-src 'self' data: https:; connect-src 'self'; font-src 'self' data: https://fonts.bunny.net https://cdn.jsdelivr.net; object-src 'none'; base-uri 'self'; frame-ancestors 'none'; form-action 'self'"
            );
        }
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Permissions-Policy', 'camera=(), geolocation=(), microphone=(), payment=(), usb=()');

        return $response;
    }
}
