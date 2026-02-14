<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;
use Filament\Support\Contracts\HasColor;

enum ServiceOrderStatus: string implements HasLabel, HasColor
{
    case SCHEDULED = 'scheduled';
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
    case AWAITING_PAYMENT = 'awaiting_payment';
    case PAID = 'paid';
    case CANCELLED = 'cancelled';
    case BILLED = 'billed';

    public function getLabel(): string
    {
        return match ($this) {
            self::SCHEDULED => 'Agendada',
            self::IN_PROGRESS => 'Em Execução',
            self::COMPLETED => 'Concluída',
            self::AWAITING_PAYMENT => 'Aguardando Pagamento',
            self::PAID => 'Paga',
            self::CANCELLED => 'Cancelada',
            self::BILLED => 'Faturada',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::SCHEDULED => 'info',
            self::IN_PROGRESS => 'warning',
            self::COMPLETED => 'success',
            self::AWAITING_PAYMENT => 'warning',
            self::PAID => 'success',
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
            self::AWAITING_PAYMENT => 'heroicon-o-clock',
            self::PAID => 'heroicon-o-check-circle',
            self::CANCELLED => 'heroicon-o-x-mark',
            self::BILLED => 'heroicon-o-banknotes',
        };
    }
}
