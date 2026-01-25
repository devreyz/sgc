<?php

namespace App\Enums;

enum StockMovementReason: string
{
    case COMPRA = 'compra';
    case PRODUCAO = 'producao';
    case VENDA = 'venda';
    case TRANSFERENCIA = 'transferencia';
    case PERDA = 'perda';
    case AJUSTE_INVENTARIO = 'ajuste_inventario';
    case DEVOLUCAO = 'devolucao';
    case OUTRO = 'outro';

    public function label(): string
    {
        return match ($this) {
            self::COMPRA => 'Compra',
            self::PRODUCAO => 'Produção',
            self::VENDA => 'Venda',
            self::TRANSFERENCIA => 'Transferência',
            self::PERDA => 'Perda',
            self::AJUSTE_INVENTARIO => 'Ajuste de Inventário',
            self::DEVOLUCAO => 'Devolução',
            self::OUTRO => 'Outro',
        };
    }
}
