<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;
use Filament\Support\Contracts\HasColor;

enum CollectivePurchaseStatus: string implements HasLabel, HasColor
{
    case OPEN = 'open';
    case CLOSED = 'closed';
    case ORDERED = 'ordered';
    case IN_TRANSIT = 'in_transit';
    case RECEIVED = 'received';
    case DISTRIBUTING = 'distributing';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::OPEN => 'Aberta para Pedidos',
            self::CLOSED => 'Fechada para Pedidos',
            self::ORDERED => 'Pedido Feito',
            self::IN_TRANSIT => 'Em Trânsito',
            self::RECEIVED => 'Recebida',
            self::DISTRIBUTING => 'Em Distribuição',
            self::COMPLETED => 'Concluída',
            self::CANCELLED => 'Cancelada',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::OPEN => 'success',
            self::CLOSED => 'warning',
            self::ORDERED => 'info',
            self::IN_TRANSIT => 'primary',
            self::RECEIVED => 'success',
            self::DISTRIBUTING => 'info',
            self::COMPLETED => 'success',
            self::CANCELLED => 'danger',
        };
    }
}
