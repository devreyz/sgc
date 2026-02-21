<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum StockMovementReason: string implements HasLabel, HasColor
{
    // Entradas
    case COMPRA          = 'compra';
    case PRODUCAO        = 'producao';
    case DEVOLUCAO       = 'devolucao';
    case RECEBIMENTO     = 'recebimento';      // Recebimento avulso
    case INVENTARIO_MAIS = 'inventario_mais';  // Sobra no inventário

    // Saídas
    case VENDA           = 'venda';
    case ENTREGA         = 'entrega';          // Entrega a projeto
    case USO_INTERNO     = 'uso_interno';
    case PERDA           = 'perda';
    case QUEBRA          = 'quebra';
    case VENCIMENTO      = 'vencimento';
    case INVENTARIO_MENOS = 'inventario_menos'; // Falta no inventário

    // Ajustes
    case AJUSTE_INVENTARIO = 'ajuste_inventario';
    case CORRECAO          = 'correcao';
    case TRANSFERENCIA     = 'transferencia';
    case OUTRO             = 'outro';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::COMPRA           => 'Compra',
            self::PRODUCAO         => 'Produção',
            self::DEVOLUCAO        => 'Devolução',
            self::RECEBIMENTO      => 'Recebimento Avulso',
            self::INVENTARIO_MAIS  => 'Inventário (Sobra)',
            self::VENDA            => 'Venda',
            self::ENTREGA          => 'Entrega (Projeto)',
            self::USO_INTERNO      => 'Uso Interno',
            self::PERDA            => 'Perda',
            self::QUEBRA           => 'Quebra',
            self::VENCIMENTO       => 'Vencimento',
            self::INVENTARIO_MENOS => 'Inventário (Falta)',
            self::AJUSTE_INVENTARIO => 'Ajuste de Inventário',
            self::CORRECAO         => 'Correção',
            self::TRANSFERENCIA    => 'Transferência',
            self::OUTRO            => 'Outro',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::COMPRA, self::PRODUCAO, self::DEVOLUCAO,
            self::RECEBIMENTO, self::INVENTARIO_MAIS          => 'success',
            self::VENDA, self::ENTREGA, self::USO_INTERNO,
            self::PERDA, self::QUEBRA, self::VENCIMENTO,
            self::INVENTARIO_MENOS                           => 'danger',
            self::AJUSTE_INVENTARIO, self::CORRECAO,
            self::TRANSFERENCIA, self::OUTRO                 => 'warning',
        };
    }

    /** Motivos permitidos para ajuste manual (requer permissão stock.adjust) */
    public static function adjustableReasons(): array
    {
        return [
            self::PERDA,
            self::QUEBRA,
            self::VENCIMENTO,
            self::USO_INTERNO,
            self::CORRECAO,
            self::AJUSTE_INVENTARIO,
        ];
    }
}
