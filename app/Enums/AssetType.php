<?php

namespace App\Enums;

enum AssetType: string
{
    case TRATOR = 'trator';
    case CAMINHAO = 'caminhao';
    case VEICULO = 'veiculo';
    case IMPLEMENTO = 'implemento';
    case EQUIPAMENTO = 'equipamento';
    case IMOVEL = 'imovel';
    case OUTRO = 'outro';

    public function label(): string
    {
        return match ($this) {
            self::TRATOR => 'Trator',
            self::CAMINHAO => 'Caminhão',
            self::VEICULO => 'Veículo',
            self::IMPLEMENTO => 'Implemento',
            self::EQUIPAMENTO => 'Equipamento',
            self::IMOVEL => 'Imóvel',
            self::OUTRO => 'Outro',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::TRATOR => 'heroicon-o-cog',
            self::CAMINHAO => 'heroicon-o-truck',
            self::VEICULO => 'heroicon-o-truck',
            self::IMPLEMENTO => 'heroicon-o-wrench',
            self::EQUIPAMENTO => 'heroicon-o-cpu-chip',
            self::IMOVEL => 'heroicon-o-building-office',
            self::OUTRO => 'heroicon-o-cube',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::TRATOR => 'success',
            self::CAMINHAO => 'info',
            self::VEICULO => 'primary',
            self::IMPLEMENTO => 'warning',
            self::EQUIPAMENTO => 'info',
            self::IMOVEL => 'gray',
            self::OUTRO => 'secondary',
        };
    }
}
