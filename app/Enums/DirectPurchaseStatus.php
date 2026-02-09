<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;
use Filament\Support\Contracts\HasColor;

enum DirectPurchaseStatus: string implements HasLabel, HasColor
{
    case DRAFT = 'draft';
    case REQUESTED = 'requested';
    case APPROVED = 'approved';
    case ORDERED = 'ordered';
    case PARTIAL_RECEIVED = 'partial_received';
    case RECEIVED = 'received';
    case CANCELLED = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::DRAFT => 'Rascunho',
            self::REQUESTED => 'Solicitada',
            self::APPROVED => 'Aprovada',
            self::ORDERED => 'Pedido Feito',
            self::PARTIAL_RECEIVED => 'Parcialmente Recebida',
            self::RECEIVED => 'Recebida',
            self::CANCELLED => 'Cancelada',
        };
    }

    public function getColor(): string | array | null
    {
        return match ($this) {
            self::DRAFT => 'gray',
            self::REQUESTED => 'warning',
            self::APPROVED => 'info',
            self::ORDERED => 'primary',
            self::PARTIAL_RECEIVED => 'info',
            self::RECEIVED => 'success',
            self::CANCELLED => 'danger',
        };
    }
}
