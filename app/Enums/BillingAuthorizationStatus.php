<?php

namespace App\Enums;

enum BillingAuthorizationStatus: string
{
    case SENT = 'sent';
    case AUTHORIZED = 'authorized';
    case CORRECTION_REQUESTED = 'correction_requested';
    case INVALIDATED = 'invalidated';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::SENT => 'Aguardando organização',
            self::AUTHORIZED => 'Autorizada',
            self::CORRECTION_REQUESTED => 'Correção solicitada',
            self::INVALIDATED => 'Autorização invalidada',
            self::CANCELLED => 'Cancelada',
        };
    }

    public function isRespondable(): bool
    {
        return $this === self::SENT;
    }
}
