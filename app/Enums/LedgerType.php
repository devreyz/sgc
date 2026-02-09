<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;
use Filament\Support\Contracts\HasColor;

enum LedgerType: string implements HasLabel, HasColor
{
    case CREDIT = 'credit';
    case DEBIT = 'debit';

    public function getLabel(): string
    {
        return match ($this) {
            self::CREDIT => 'Crédito',
            self::DEBIT => 'Débito',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::CREDIT => 'success',
            self::DEBIT => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::CREDIT => 'heroicon-o-arrow-up-circle',
            self::DEBIT => 'heroicon-o-arrow-down-circle',
        };
    }
}
