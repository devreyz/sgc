<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InjectGlobalLoadingOverlay
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->is('admin', 'admin/*', 'super-admin', 'super-admin/*')) {
            return $response;
        }

        $contentType = (string) $response->headers->get('Content-Type', '');

        if (! str_contains($contentType, 'text/html') || ! method_exists($response, 'getContent')) {
            return $response;
        }

        $content = $response->getContent();

        if (! is_string($content) || str_contains($content, 'sgc-loading-overlay.css')) {
            return $response;
        }

        $assets = '<link rel="stylesheet" href="/assets/sgc-loading-overlay.css">'
            .'<script src="/assets/sgc-loading-overlay.js" defer></script>';

        if (str_contains($content, '</head>')) {
            $response->setContent(str_replace('</head>', $assets.'</head>', $content));
        }

        return $response;
    }
}
