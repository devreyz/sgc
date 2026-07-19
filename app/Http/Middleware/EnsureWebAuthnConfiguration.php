<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class EnsureWebAuthnConfiguration
{
    public function handle(Request $request, Closure $next): Response
    {
        $rpId = (string) config('passkeys.relying_party_id');
        $origins = (array) config('passkeys.allowed_origins');

        if ($rpId === '' || $origins === []) {
            throw new RuntimeException('WebAuthn RP ID e origins precisam ser configurados.');
        }

        if (app()->environment('production')) {
            if (! $request->isSecure()) {
                abort(400, 'HTTPS e obrigatorio para autenticacao.');
            }

            foreach ($origins as $origin) {
                if (parse_url((string) $origin, PHP_URL_SCHEME) !== 'https') {
                    throw new RuntimeException('Origins WebAuthn de producao devem usar HTTPS.');
                }
            }
        } elseif ($rpId !== 'localhost' && ! $request->isSecure()) {
            abort(400, 'WebAuthn exige HTTPS fora de localhost.');
        }

        return $next($request);
    }
}
