<?php

namespace App\Enums;

enum LedgerType: string
{
    case CREDIT = 'credit';
    case DEBIT = 'debit';

    public function label(): string
    {
        return match ($this) {
            self::CREDIT => 'Crédito',
            self::DEBIT => 'Débito',
        };
    }

    public function color(): string
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
