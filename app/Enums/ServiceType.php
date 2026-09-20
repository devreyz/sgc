<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ServiceType: string implements HasColor, HasLabel
{
    case HORA_MAQUINA = 'hora_maquina';
    case FRETE = 'frete';
    case CONSULTORIA = 'consultoria';
    case BENEFICIAMENTO = 'beneficiamento';
    case MAO_DE_OBRA = 'mao_de_obra';
    case MANUTENCAO = 'manutencao';
    case LOCACAO = 'locacao';
    case VISITA_TECNICA = 'visita_tecnica';
    case OUTRO = 'outro';

    public function getLabel(): string
    {
        return match ($this) {
            self::HORA_MAQUINA => 'Hora Máquina',
            self::FRETE => 'Frete',
            self::CONSULTORIA => 'Consultoria',
            self::BENEFICIAMENTO => 'Beneficiamento',
            self::MAO_DE_OBRA => 'Mão de obra / diária',
            self::MANUTENCAO => 'Manutenção e reparo',
            self::LOCACAO => 'Locação de equipamento',
            self::VISITA_TECNICA => 'Visita técnica',
            self::OUTRO => 'Outro',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::HORA_MAQUINA => 'heroicon-o-cog',
            self::FRETE => 'heroicon-o-truck',
            self::CONSULTORIA => 'heroicon-o-academic-cap',
            self::BENEFICIAMENTO => 'heroicon-o-beaker',
            self::MAO_DE_OBRA => 'heroicon-o-user-group',
            self::MANUTENCAO => 'heroicon-o-wrench-screwdriver',
            self::LOCACAO => 'heroicon-o-calendar-days',
            self::VISITA_TECNICA => 'heroicon-o-map-pin',
            self::OUTRO => 'heroicon-o-wrench-screwdriver',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::HORA_MAQUINA => 'success',
            self::FRETE => 'info',
            self::CONSULTORIA => 'primary',
            self::BENEFICIAMENTO => 'warning',
            self::MAO_DE_OBRA => 'info',
            self::MANUTENCAO => 'warning',
            self::LOCACAO => 'primary',
            self::VISITA_TECNICA => 'success',
            self::OUTRO => 'secondary',
        };
    }
}
