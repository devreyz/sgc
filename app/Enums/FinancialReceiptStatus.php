<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum FinancialReceiptStatus: string implements HasColor, HasLabel
{
    case DRAFT = 'draft';
    case ISSUED = 'issued';
    case CANCELLED = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::DRAFT => 'Rascunho',
            self::ISSUED => 'Emitido',
            self::CANCELLED => 'Cancelado',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::DRAFT => 'gray',
            self::ISSUED => 'success',
            self::CANCELLED => 'danger',
        };
    }
}
