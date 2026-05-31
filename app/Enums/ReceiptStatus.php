<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ReceiptStatus: string implements HasColor, HasLabel
{
    /** Rascunho — gerado mas ainda sem snapshot financeiro congelado */
    case DRAFT           = 'draft';
    case PENDING_PAYMENT  = 'pending_payment';
    case PARTIALLY_PAID   = 'partially_paid';
    case PAID             = 'paid';

    public function getLabel(): string
    {
        return match ($this) {
            self::DRAFT           => 'Rascunho',
            self::PENDING_PAYMENT => 'Aguardando Pagamento',
            self::PARTIALLY_PAID  => 'Parcialmente Pago',
            self::PAID            => 'Pago',
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

    /** Comprovante pode ter seus itens editados? */
    public function isEditable(): bool
    {
        return $this === self::DRAFT || $this === self::PENDING_PAYMENT;
    }

    /** Comprovante está imutável (após pagamento)? */
    public function isLocked(): bool
    {
        return $this === self::PAID;
    }
}
