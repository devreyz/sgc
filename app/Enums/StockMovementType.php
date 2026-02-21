<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Contracts\HasIcon;

enum StockMovementType: string implements HasLabel, HasColor, HasIcon
{
    case ENTRADA = 'entrada';
    case SAIDA   = 'saida';
    case AJUSTE  = 'ajuste';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::ENTRADA => 'Entrada',
            self::SAIDA   => 'Saída',
            self::AJUSTE  => 'Ajuste',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::ENTRADA => 'success',
            self::SAIDA   => 'danger',
            self::AJUSTE  => 'warning',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::ENTRADA => 'heroicon-o-arrow-down-circle',
            self::SAIDA   => 'heroicon-o-arrow-up-circle',
            self::AJUSTE  => 'heroicon-o-adjustments-horizontal',
        };
    }

    /** Sinal para cálculo do saldo: +1 ou -1 */
    public function signal(): int
    {
        return match ($this) {
            self::ENTRADA => 1,
            self::SAIDA   => -1,
            self::AJUSTE  => 1, // ajuste define stock_after diretamente
        };
    }
}
