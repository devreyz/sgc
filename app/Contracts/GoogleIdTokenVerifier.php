<?php

namespace App\Contracts;

use App\ValueObjects\GoogleIdentity;

interface GoogleIdTokenVerifier
{
    public function verify(string $idToken, string $expectedNonce): GoogleIdentity;
}
