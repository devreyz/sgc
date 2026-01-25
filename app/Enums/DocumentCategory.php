<?php

namespace App\Enums;

enum DocumentCategory: string
{
    case CONTRATO = 'contrato';
    case NOTA_FISCAL = 'nota_fiscal';
    case COMPROVANTE = 'comprovante';
    case DAP_CAF = 'dap_caf';
    case DOCUMENTO_PESSOAL = 'documento_pessoal';
    case LICENCA = 'licenca';
    case RELATORIO = 'relatorio';
    case FOTO = 'foto';
    case OUTRO = 'outro';

    public function label(): string
    {
        return match ($this) {
            self::CONTRATO => 'Contrato',
            self::NOTA_FISCAL => 'Nota Fiscal',
            self::COMPROVANTE => 'Comprovante',
            self::DAP_CAF => 'DAP/CAF',
            self::DOCUMENTO_PESSOAL => 'Documento Pessoal',
            self::LICENCA => 'Licença',
            self::RELATORIO => 'Relatório',
            self::FOTO => 'Foto',
            self::OUTRO => 'Outro',
        };
    }
}
