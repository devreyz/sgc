<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;
use Filament\Support\Contracts\HasColor;

enum PurchaseOrderStatus: string implements HasLabel, HasColor
{
    case REQUESTED = 'requested';
    case CONFIRMED = 'confirmed';
    case ORDERED_FROM_SUPPLIER = 'ordered_from_supplier';
    case IN_TRANSIT = 'in_transit';
    case ARRIVED = 'arrived';
    case DELIVERED = 'delivered';
    case CANCELLED = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::REQUESTED => 'Solicitado',
            self::CONFIRMED => 'Confirmado',
            self::ORDERED_FROM_SUPPLIER => 'Pedido ao Fornecedor',
            self::IN_TRANSIT => 'Em Trânsito',
            self::ARRIVED => 'Recebido na Coop',
            self::DELIVERED => 'Entregue ao Associado',
            self::CANCELLED => 'Cancelado',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::REQUESTED => 'warning',
            self::CONFIRMED => 'info',
            self::ORDERED_FROM_SUPPLIER => 'primary',
            self::IN_TRANSIT => 'info',
            self::ARRIVED => 'success',
            self::DELIVERED => 'success',
            self::CANCELLED => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::REQUESTED => 'heroicon-o-clock',
            self::CONFIRMED => 'heroicon-o-check',
            self::ORDERED_FROM_SUPPLIER => 'heroicon-o-truck',
            self::IN_TRANSIT => 'heroicon-o-arrow-path',
            self::ARRIVED => 'heroicon-o-home',
            self::DELIVERED => 'heroicon-o-check-circle',
            self::CANCELLED => 'heroicon-o-x-circle',
        };
    }
}
