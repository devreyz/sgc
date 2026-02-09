<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;
use Filament\Support\Contracts\HasColor;

enum ProjectStatus: string implements HasLabel, HasColor
{
    case DRAFT = 'draft';
    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';
    case AWAITING_DELIVERY = 'awaiting_delivery';
    case DELIVERED = 'delivered';
    case AWAITING_PAYMENT = 'awaiting_payment';
    case PAYMENT_RECEIVED = 'payment_received';
    case ASSOCIATES_PAID = 'associates_paid';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::DRAFT => 'Rascunho',
            self::ACTIVE => 'Em Execução',
            self::SUSPENDED => 'Suspenso',
            self::AWAITING_DELIVERY => 'Aguardando Entrega',
            self::DELIVERED => 'Entregue ao Cliente',
            self::AWAITING_PAYMENT => 'Aguardando Pagamento',
            self::PAYMENT_RECEIVED => 'Pagamento Recebido',
            self::ASSOCIATES_PAID => 'Associados Pagos',
            self::COMPLETED => 'Concluído',
            self::CANCELLED => 'Cancelado',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::DRAFT => 'gray',
            self::ACTIVE => 'success',
            self::SUSPENDED => 'warning',
            self::AWAITING_DELIVERY => 'info',
            self::DELIVERED => 'primary',
            self::AWAITING_PAYMENT => 'warning',
            self::PAYMENT_RECEIVED => 'success',
            self::ASSOCIATES_PAID => 'info',
            self::COMPLETED => 'success',
            self::CANCELLED => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::DRAFT => 'heroicon-o-document',
            self::ACTIVE => 'heroicon-o-play',
            self::SUSPENDED => 'heroicon-o-pause',
            self::AWAITING_DELIVERY => 'heroicon-o-truck',
            self::DELIVERED => 'heroicon-o-check-badge',
            self::AWAITING_PAYMENT => 'heroicon-o-clock',
            self::PAYMENT_RECEIVED => 'heroicon-o-banknotes',
            self::ASSOCIATES_PAID => 'heroicon-o-users',
            self::COMPLETED => 'heroicon-o-check-circle',
            self::CANCELLED => 'heroicon-o-x-mark',
        };
    }
}
