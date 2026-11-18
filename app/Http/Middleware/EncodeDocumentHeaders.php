<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EncodeDocumentHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        // Keep document metadata ASCII on the wire, including accented names.
        foreach (['Title', 'Path', 'Origin'] as $field) {
            $name = 'X-SGC-Document-'.$field;
            if ($response->headers->has($name)) {
                $response->headers->set($name, rawurlencode($response->headers->get($name)));
            }
        }

        return $response;
    }
}
