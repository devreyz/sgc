<?php

namespace App\Enums;

enum FiscalAmountSource: string
{
    case AUTHORIZED_GROSS = 'authorized_gross';
    case AUTHORIZED_FINAL = 'authorized_final';

    public function label(): string
    {
        return match ($this) {
            self::AUTHORIZED_GROSS => 'Total bruto autorizado',
            self::AUTHORIZED_FINAL => 'Total final autorizado da cobrança',
        };
    }
}
