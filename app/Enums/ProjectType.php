<?php

namespace App\Enums;

enum ProjectType: string
{
    case PNAE = 'pnae';
    case PAA = 'paa';
    case CONTRATO = 'contrato';
    case LICITACAO = 'licitacao';
    case OUTRO = 'outro';

    public function label(): string
    {
        return match ($this) {
            self::PNAE => 'PNAE',
            self::PAA => 'PAA',
            self::CONTRATO => 'Contrato',
            self::LICITACAO => 'Licitação',
            self::OUTRO => 'Outro',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PNAE => 'success',
            self::PAA => 'info',
            self::CONTRATO => 'warning',
            self::LICITACAO => 'primary',
            self::OUTRO => 'gray',
        };
    }
}
