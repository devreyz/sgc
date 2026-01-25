<?php

namespace App\Enums;

enum StockMovementType: string
{
    case ENTRADA = 'entrada';
    case SAIDA = 'saida';
    case AJUSTE = 'ajuste';

    public function label(): string
    {
        return match ($this) {
            self::ENTRADA => 'Entrada',
            self::SAIDA => 'Saída',
            self::AJUSTE => 'Ajuste',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::ENTRADA => 'success',
            self::SAIDA => 'danger',
            self::AJUSTE => 'warning',
        };
    }
}
