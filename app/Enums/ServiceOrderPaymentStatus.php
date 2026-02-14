<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;
use Filament\Support\Contracts\HasColor;

enum ServiceOrderPaymentStatus: string implements HasLabel, HasColor
{
    case PAID = 'paid';
    case PENDING = 'pending';
    case BILLED = 'billed';
    case CANCELLED = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::PAID => 'Pago',
            self::PENDING => 'Pendente',
            self::BILLED => 'Faturado',
            self::CANCELLED => 'Cancelado',
        };
    }

    public function getColor(): string | array | null
    {
        return match ($this) {
            self::PAID => 'success',
            self::PENDING => 'warning',
            self::BILLED => 'success',
            self::CANCELLED => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::PAID => 'heroicon-o-currency-dollar',
            self::PENDING => 'heroicon-o-clock',
            self::BILLED => 'heroicon-o-check-circle',
            self::CANCELLED => 'heroicon-o-x-circle',
        };
    }
}
