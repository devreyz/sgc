<?php

namespace App\Enums;

enum AssetStatus: string
{
    case DISPONIVEL = 'disponivel';
    case EM_USO = 'em_uso';
    case MANUTENCAO = 'manutencao';
    case INATIVO = 'inativo';
    case VENDIDO = 'vendido';

    public function label(): string
    {
        return match ($this) {
            self::DISPONIVEL => 'Disponível',
            self::EM_USO => 'Em Uso',
            self::MANUTENCAO => 'Em Manutenção',
            self::INATIVO => 'Inativo',
            self::VENDIDO => 'Vendido',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::DISPONIVEL => 'success',
            self::EM_USO => 'info',
            self::MANUTENCAO => 'warning',
            self::INATIVO => 'gray',
            self::VENDIDO => 'danger',
        };
    }
}
