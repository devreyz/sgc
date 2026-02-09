<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;
use Filament\Support\Contracts\HasColor;

enum DirectPurchasePaymentStatus: string implements HasLabel, HasColor
{
    case PENDING = 'pending';
    case PARTIAL = 'partial';
    case PAID = 'paid';

    public function getLabel(): string
    {
        return match ($this) {
            self::PENDING => 'Pendente',
            self::PARTIAL => 'Parcial',
            self::PAID => 'Pago',
        };
    }

    public function getColor(): string | array | null
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::PARTIAL => 'info',
            self::PAID => 'success',
        };
    }
}
