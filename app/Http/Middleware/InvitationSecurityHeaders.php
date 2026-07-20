<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InvitationSecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $viteHttpOrigin = null;
        $viteWebSocketOrigin = null;

        if (app()->environment('local') && is_file(public_path('hot'))) {
            $hotUrl = trim((string) file_get_contents(public_path('hot')));
            $parts = parse_url($hotUrl);
            $host = strtolower((string) ($parts['host'] ?? ''));
            $scheme = strtolower((string) ($parts['scheme'] ?? ''));

            if (in_array($host, ['127.0.0.1', 'localhost', '::1'], true)
                && in_array($scheme, ['http', 'https'], true)) {
                $formattedHost = $host === '::1' ? '[::1]' : $host;
                $port = isset($parts['port']) ? ':'.(int) $parts['port'] : '';
                $viteHttpOrigin = $scheme.'://'.$formattedHost.$port;
                $viteWebSocketOrigin = ($scheme === 'https' ? 'wss' : 'ws').'://'.$formattedHost.$port;
            }
        }

        $scriptSources = "'self' 'unsafe-inline'".($viteHttpOrigin ? ' '.$viteHttpOrigin : '');
        $styleSources = "'self' 'unsafe-inline'".($viteHttpOrigin ? ' '.$viteHttpOrigin : '');
        $connectSources = "'self'".($viteHttpOrigin ? ' '.$viteHttpOrigin.' '.$viteWebSocketOrigin : '');

        $response->headers->set('Cache-Control', 'no-store, private, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Content-Security-Policy', "default-src 'self'; script-src {$scriptSources}; style-src {$styleSources}; img-src 'self' data:; connect-src {$connectSources}; frame-ancestors 'none'; base-uri 'none'; form-action 'self'");
        $response->headers->set('Permissions-Policy', 'publickey-credentials-get=(self), publickey-credentials-create=(self), camera=(), microphone=(), geolocation=()');

        return $response;
    }
}
