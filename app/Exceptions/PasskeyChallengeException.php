<?php

namespace App\Exceptions;

use RuntimeException;

class PasskeyChallengeException extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct('Passkey challenge rejected.');
    }
}
