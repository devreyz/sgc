<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ProjectType: string implements HasLabel, HasColor
{
    case PNAE = 'pnae';
    case PAA = 'paa';
    case CONTRATO = 'contrato';
    case LICITACAO = 'licitacao';
    case OUTRO = 'outro';

    public function getLabel(): string
    {
        return match ($this) {
            self::PNAE => 'PNAE',
            self::PAA => 'PAA',
            self::CONTRATO => 'Contrato',
            self::LICITACAO => 'Licitação',
            self::OUTRO => 'Outro',
        };
    }

    public function getColor(): string|array|null
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
