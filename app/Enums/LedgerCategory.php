<?php

namespace App\Enums;

enum LedgerCategory: string
{
    case PRODUCAO = 'producao';
    case TAXA_ADMIN = 'taxa_admin';
    case COMPRA_INSUMO = 'compra_insumo';
    case SERVICO = 'servico';
    case ADIANTAMENTO = 'adiantamento';
    case DEVOLUCAO = 'devolucao';
    case AJUSTE = 'ajuste';
    case TRANSFERENCIA = 'transferencia';
    case OUTRO = 'outro';

    public function label(): string
    {
        return match ($this) {
            self::PRODUCAO => 'Produção',
            self::TAXA_ADMIN => 'Taxa Administrativa',
            self::COMPRA_INSUMO => 'Compra de Insumo',
            self::SERVICO => 'Serviço',
            self::ADIANTAMENTO => 'Adiantamento',
            self::DEVOLUCAO => 'Devolução',
            self::AJUSTE => 'Ajuste',
            self::TRANSFERENCIA => 'Transferência',
            self::OUTRO => 'Outro',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PRODUCAO => 'success',
            self::TAXA_ADMIN => 'warning',
            self::COMPRA_INSUMO => 'danger',
            self::SERVICO => 'info',
            self::ADIANTAMENTO => 'primary',
            self::DEVOLUCAO => 'success',
            self::AJUSTE => 'gray',
            self::TRANSFERENCIA => 'primary',
            self::OUTRO => 'gray',
        };
    }
}
