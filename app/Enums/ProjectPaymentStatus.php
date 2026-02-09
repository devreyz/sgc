<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;
use Filament\Support\Contracts\HasColor;

enum ProjectPaymentStatus: string implements HasLabel, HasColor
{
    case PENDING = 'pending';
    case DEPOSITED = 'deposited';
    case PAID = 'paid';
    case CANCELLED = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::PENDING => 'Pendente',
            self::DEPOSITED => 'Depositado',
            self::PAID => 'Pago',
            self::CANCELLED => 'Cancelado',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::DEPOSITED => 'info',
            self::PAID => 'success',
            self::CANCELLED => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::PENDING => 'heroicon-o-clock',
            self::DEPOSITED => 'heroicon-o-building-library',
            self::PAID => 'heroicon-o-check-circle',
            self::CANCELLED => 'heroicon-o-x-circle',
        };
    }
}
