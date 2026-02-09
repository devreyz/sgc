<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;
use Filament\Support\Contracts\HasColor;

enum CashMovementType: string implements HasLabel, HasColor
{
    case INCOME = 'income';
    case EXPENSE = 'expense';
    case TRANSFER = 'transfer';

    public function getLabel(): string
    {
        return match ($this) {
            self::INCOME => 'Entrada',
            self::EXPENSE => 'Saída',
            self::TRANSFER => 'Transferência',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::INCOME => 'success',
            self::EXPENSE => 'danger',
            self::TRANSFER => 'info',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::INCOME => 'heroicon-o-arrow-down-circle',
            self::EXPENSE => 'heroicon-o-arrow-up-circle',
            self::TRANSFER => 'heroicon-o-arrow-path',
        };
    }
}
