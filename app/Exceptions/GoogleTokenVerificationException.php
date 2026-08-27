<?php

namespace App\Exceptions;

use RuntimeException;

class GoogleTokenVerificationException extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct('Google ID token verification failed.');
    }
}
