<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum BillingStatus: string implements HasColor, HasLabel
{
    case UNBILLED = 'unbilled';
    case BILLED   = 'billed';
    case PAID     = 'paid';

    public function getLabel(): string
    {
        return match ($this) {
            self::UNBILLED => 'Não Faturado',
            self::BILLED   => 'Faturado',
            self::PAID     => 'Pago',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::UNBILLED => 'gray',
            self::BILLED   => 'warning',
            self::PAID     => 'success',
        };
    }
}
