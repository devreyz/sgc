<?php

namespace App\Enums;

enum ProductType: string
{
    case PRODUCAO = 'producao';
    case INSUMO = 'insumo';
    case REVENDA = 'revenda';

    public function label(): string
    {
        return match ($this) {
            self::PRODUCAO => 'Produção',
            self::INSUMO => 'Insumo',
            self::REVENDA => 'Revenda',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PRODUCAO => 'success',
            self::INSUMO => 'warning',
            self::REVENDA => 'info',
        };
    }
}
