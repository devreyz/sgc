<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;
use Filament\Support\Contracts\HasColor;

enum ProjectStatus: string implements HasLabel, HasColor
{
    case DRAFT             = 'draft';
    case ACTIVE            = 'active';
    case SUSPENDED         = 'suspended';
    case DELIVERIES_CLOSED = 'deliveries_closed';
    case COMPLETED         = 'completed';
    case CANCELLED         = 'cancelled';
    case ARCHIVED          = 'archived';

    public function getLabel(): string
    {
        return match ($this) {
            self::DRAFT             => 'Rascunho',
            self::ACTIVE            => 'Em Execução',
            self::SUSPENDED         => 'Suspenso',
            self::DELIVERIES_CLOSED => 'Entregas Encerradas',
            self::COMPLETED         => 'Concluído',
            self::CANCELLED         => 'Cancelado',
            self::ARCHIVED          => 'Arquivado',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::DRAFT             => 'gray',
            self::ACTIVE            => 'success',
            self::SUSPENDED         => 'warning',
            self::DELIVERIES_CLOSED => 'info',
            self::COMPLETED         => 'success',
            self::CANCELLED         => 'danger',
            self::ARCHIVED          => 'gray',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::DRAFT             => 'heroicon-o-document',
            self::ACTIVE            => 'heroicon-o-play',
            self::SUSPENDED         => 'heroicon-o-pause',
            self::DELIVERIES_CLOSED => 'heroicon-o-archive-box',
            self::COMPLETED         => 'heroicon-o-check-circle',
            self::CANCELLED         => 'heroicon-o-x-mark',
            self::ARCHIVED          => 'heroicon-o-archive-box-arrow-down',
        };
    }

    /** Aceita novas recepções de entregas dos associados */
    public function acceptsDeliveries(): bool
    {
        return $this === self::ACTIVE;
    }

    /** Permite distribuições e faturamentos periódicos */
    public function allowsFinancial(): bool
    {
        return in_array($this, [self::ACTIVE, self::DELIVERIES_CLOSED]);
    }
}
