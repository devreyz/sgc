<?php

namespace App\Enums;

enum ServiceType: string
{
    case HORA_MAQUINA = 'hora_maquina';
    case FRETE = 'frete';
    case CONSULTORIA = 'consultoria';
    case BENEFICIAMENTO = 'beneficiamento';
    case OUTRO = 'outro';

    public function label(): string
    {
        return match ($this) {
            self::HORA_MAQUINA => 'Hora Máquina',
            self::FRETE => 'Frete',
            self::CONSULTORIA => 'Consultoria',
            self::BENEFICIAMENTO => 'Beneficiamento',
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
            self::OUTRO => 'heroicon-o-wrench-screwdriver',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::HORA_MAQUINA => 'success',
            self::FRETE => 'info',
            self::CONSULTORIA => 'primary',
            self::BENEFICIAMENTO => 'warning',
            self::OUTRO => 'secondary',
        };
    }
}
