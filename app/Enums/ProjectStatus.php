<?php

namespace App\Enums;

enum ProjectStatus: string
{
    case DRAFT = 'draft';
    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Rascunho',
            self::ACTIVE => 'Em Execução',
            self::SUSPENDED => 'Suspenso',
            self::COMPLETED => 'Concluído',
            self::CANCELLED => 'Cancelado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::DRAFT => 'gray',
            self::ACTIVE => 'success',
            self::SUSPENDED => 'warning',
            self::COMPLETED => 'info',
            self::CANCELLED => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::DRAFT => 'heroicon-o-document',
            self::ACTIVE => 'heroicon-o-play',
            self::SUSPENDED => 'heroicon-o-pause',
            self::COMPLETED => 'heroicon-o-check',
            self::CANCELLED => 'heroicon-o-x-mark',
        };
    }
}
