<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum CustomerReceiptStatus: string implements HasColor, HasLabel
{
    case DRAFT           = 'draft';
    case PENDING_PAYMENT = 'pending_payment';
    case PARTIALLY_PAID  = 'partially_paid';
    case PAID            = 'paid';

    public function getLabel(): string
    {
        return match ($this) {
            self::DRAFT           => 'Rascunho',
            self::PENDING_PAYMENT => 'Aguardando Recebimento',
            self::PARTIALLY_PAID  => 'Parcialmente Recebido',
            self::PAID            => 'Recebido',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::DRAFT           => 'gray',
            self::PENDING_PAYMENT => 'warning',
            self::PARTIALLY_PAID  => 'info',
            self::PAID            => 'success',
        };
    }

    /** Permite edição/exclusão somente enquanto rascunho. */
    public function isEditable(): bool
    {
        return $this === self::DRAFT;
    }

    /** Bloqueado após emissão de cobrança ou recebimento. */
    public function isLocked(): bool
    {
        return ! $this->isEditable();
    }
}
