<?php

namespace App\Support;

/**
 * Normalizes monetary values at the point where they become payable.
 * Document calculations may retain four decimal places, but payments and
 * cash movements are always settled in cents.
 */
final class FinancialAmount
{
    public static function cents(string|int|float|null $amount): string
    {
        $value = trim((string) ($amount ?? '0'));
        $value = str_replace([' ', "\xC2\xA0"], '', $value);

        if (str_contains($value, ',')) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        }

        if (! preg_match('/^(-?)(\d+)(?:\.(\d+))?$/', $value, $matches)) {
            return '0.00';
        }

        $integer = ltrim($matches[2], '0');
        $integer = $integer === '' ? '0' : $integer;
        $fraction = str_pad($matches[3] ?? '', 3, '0');
        $normalized = $integer.'.'.substr($fraction, 0, 2);

        if ((int) $fraction[2] >= 5) {
            $normalized = bcadd($normalized, '0.01', 2);
        }

        return $matches[1] === '-' && bccomp($normalized, '0.00', 2) !== 0
            ? bcsub('0.00', $normalized, 2)
            : $normalized;
    }

    public static function remaining(string|int|float|null $total, string|int|float|null $paid): string
    {
        $remaining = bcsub(self::cents($total), self::cents($paid), 2);

        return bccomp($remaining, '0.00', 2) <= 0 ? '0.00' : $remaining;
    }
}
