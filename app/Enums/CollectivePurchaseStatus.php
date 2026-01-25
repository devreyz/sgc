<?php

namespace App\Enums;

enum CollectivePurchaseStatus: string
{
    case OPEN = 'open';
    case CLOSED = 'closed';
    case ORDERED = 'ordered';
    case IN_TRANSIT = 'in_transit';
    case RECEIVED = 'received';
    case DISTRIBUTING = 'distributing';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    public function label(): string
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

    public function color(): string
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
