<?php

namespace App\Auth;

use Exception;
use Firebase\JWT\JWT;
use Illuminate\Support\Arr;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\InvalidStateException;

class SecureGoogleProvider extends GoogleProvider
{
    /**
     * Google and the application server may cross a second boundary while the
     * authorization code is exchanged. Keep the tolerance small and restore
     * Firebase JWT's process-wide static value for long-running workers.
     */
    protected function getUserFromJwtToken($idToken)
    {
        $previousLeeway = JWT::$leeway;
        JWT::$leeway = max(0, min(60, (int) config('security.google_jwt_clock_skew_seconds', 10)));

        try {
            return parent::getUserFromJwtToken($idToken);
        } finally {
            JWT::$leeway = $previousLeeway;
        }
    }

    public function user()
    {
        if ($this->user) {
            return $this->user;
        }

        if ($this->hasInvalidState()) {
            throw new InvalidStateException;
        }

        $response = $this->getAccessTokenResponse($this->getCode());
        $idToken = Arr::get($response, 'id_token');

        if (! is_string($idToken) || $idToken === '') {
            throw new Exception('Google did not return an ID token.');
        }

        $claims = $this->getUserByToken($idToken);
        $expectedNonce = (string) $this->request->session()->pull('google_oidc_nonce', '');

        if ($expectedNonce === '' || ! hash_equals($expectedNonce, (string) Arr::get($claims, 'nonce', ''))) {
            throw new Exception('Invalid Google nonce.');
        }

        if (Arr::get($claims, 'email_verified') !== true) {
            throw new Exception('Google email is not verified.');
        }

        if (! is_string(Arr::get($claims, 'sub')) || Arr::get($claims, 'sub') === '') {
            throw new Exception('Google subject is missing.');
        }

        return $this->userInstance($response, $claims);
    }
}
