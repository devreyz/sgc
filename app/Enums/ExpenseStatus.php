<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;
use Filament\Support\Contracts\HasColor;

enum ExpenseStatus: string implements HasLabel, HasColor
{
    case PENDING = 'pending';
    case PAID = 'paid';
    case CANCELLED = 'cancelled';
    case OVERDUE = 'overdue';

    public function getLabel(): string
    {
        return match ($this) {
            self::PENDING => 'Pendente',
            self::PAID => 'Pago',
            self::CANCELLED => 'Cancelado',
            self::OVERDUE => 'Vencido',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::PAID => 'success',
            self::CANCELLED => 'gray',
            self::OVERDUE => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::PENDING => 'heroicon-o-clock',
            self::PAID => 'heroicon-o-check-circle',
            self::CANCELLED => 'heroicon-o-x-circle',
            self::OVERDUE => 'heroicon-o-exclamation-triangle',
        };
    }
}
