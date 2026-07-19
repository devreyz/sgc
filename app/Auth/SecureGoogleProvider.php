<?php

namespace App\Auth;

use Exception;
use Illuminate\Support\Arr;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\InvalidStateException;

class SecureGoogleProvider extends GoogleProvider
{
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
