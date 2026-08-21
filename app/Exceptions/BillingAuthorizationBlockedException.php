<?php

namespace App\Exceptions;

use RuntimeException;

class BillingAuthorizationBlockedException extends RuntimeException
{
    /** @param array<int, array{code: string, message: string}> $issues */
    public function __construct(public readonly array $issues, string $message = 'Não foi possível concluir a autorização.')
    {
        parent::__construct($message);
    }
}
