<?php

namespace App\Enums;

enum DeliveryConferenceStatus: string
{
    case DRAFT = 'draft';
    case ISSUED = 'issued';
    case APPROVED = 'approved';
    case CORRECTION_REQUESTED = 'correction_requested';
    case REJECTED = 'rejected';
    case CANCELLED = 'cancelled';
    case SUPERSEDED = 'superseded';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Rascunho',
            self::ISSUED => 'Emitida',
            self::APPROVED => 'Aprovada',
            self::CORRECTION_REQUESTED => 'Correção necessária',
            self::REJECTED => 'Rejeitada',
            self::CANCELLED => 'Cancelada',
            self::SUPERSEDED => 'Substituída',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::DRAFT => 'gray',
            self::ISSUED => 'info',
            self::APPROVED => 'success',
            self::CORRECTION_REQUESTED => 'warning',
            self::REJECTED, self::CANCELLED => 'danger',
            self::SUPERSEDED => 'gray',
        };
    }
}
