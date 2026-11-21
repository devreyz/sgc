<?php

namespace App\Services\Services;

final class ServiceMoney
{
    public static function cents(string|int|float $amount): int
    {
        $decimal = is_float($amount) ? number_format($amount, 2, '.', '') : (string) $amount;
        [$whole, $fraction] = array_pad(explode('.', ltrim($decimal, '-'), 2), 2, '');

        return (str_starts_with($decimal, '-') ? -1 : 1)
            * ((int) $whole * 100 + (int) str_pad(substr($fraction, 0, 2), 2, '0'));
    }

    public static function decimal(int $cents): string
    {
        return ($cents < 0 ? '-' : '').sprintf('%d.%02d', intdiv(abs($cents), 100), abs($cents) % 100);
    }
}
