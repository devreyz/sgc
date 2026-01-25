<?php

namespace App\Enums;

enum ServiceOrderStatus: string
{
    case SCHEDULED = 'scheduled';
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
    case BILLED = 'billed';

    public function label(): string
    {
        return match ($this) {
            self::SCHEDULED => 'Agendada',
            self::IN_PROGRESS => 'Em Execução',
            self::COMPLETED => 'Concluída',
            self::CANCELLED => 'Cancelada',
            self::BILLED => 'Faturada',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::SCHEDULED => 'info',
            self::IN_PROGRESS => 'warning',
            self::COMPLETED => 'success',
            self::CANCELLED => 'danger',
            self::BILLED => 'primary',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::SCHEDULED => 'heroicon-o-calendar',
            self::IN_PROGRESS => 'heroicon-o-play',
            self::COMPLETED => 'heroicon-o-check',
            self::CANCELLED => 'heroicon-o-x-mark',
            self::BILLED => 'heroicon-o-banknotes',
        };
    }
}
