<?php

namespace App\Services;

use NumberFormatter;

class NumberInWordsService
{
    public function number(float|int|string|null $value): string
    {
        $number = $this->normalize($value);
        if ($number === null) {
            return '';
        }

        return $this->spell($number);
    }

    public function money(float|int|string|null $value): string
    {
        $number = $this->normalize($value);
        if ($number === null) {
            return '';
        }

        $isNegative = $number < 0;
        $totalCents = (int) round(abs($number) * 100);
        $reais = intdiv($totalCents, 100);
        $centavos = $totalCents % 100;
        $parts = [];

        if ($reais > 0 || $centavos === 0) {
            $requiresDe = $reais >= 1_000_000 && $reais % 1_000_000 === 0;
            $parts[] = $this->spell($reais)
                .($requiresDe ? ' de' : '')
                .($reais === 1 ? ' real' : ' reais');
        }

        if ($centavos > 0) {
            $parts[] = $this->spell($centavos)
                .($centavos === 1 ? ' centavo' : ' centavos');
        }

        return ($isNegative ? 'menos ' : '').implode(' e ', $parts);
    }

    public function percentage(float|int|string|null $value): string
    {
        $words = $this->number($value);

        return $words === '' ? '' : $words.' por cento';
    }

    public function quantity(
        float|int|string|null $value,
        ?string $singularUnit = null,
        ?string $pluralUnit = null,
    ): string {
        $number = $this->normalize($value);
        if ($number === null) {
            return '';
        }

        $words = $this->spell($number);
        if (! $singularUnit) {
            return $words;
        }

        $unit = abs($number) === 1.0
            ? $singularUnit
            : ($pluralUnit ?: $singularUnit);

        return $words.' '.$unit;
    }

    private function spell(float|int $value): string
    {
        if (! class_exists(NumberFormatter::class)) {
            return $this->fallback($value);
        }

        $formatter = new NumberFormatter('pt_BR', NumberFormatter::SPELLOUT);
        $formatted = $formatter->format($value);

        return $formatted === false ? $this->fallback($value) : $formatted;
    }

    private function normalize(float|int|string|null $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_float($value) || is_int($value)) {
            return is_finite((float) $value) ? (float) $value : null;
        }

        $normalized = trim($value);
        $normalized = str_ireplace(['R$', '%', "\u{00A0}", ' '], '', $normalized);

        if (str_contains($normalized, ',')) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        }

        if (! is_numeric($normalized)) {
            return null;
        }

        $number = (float) $normalized;

        return is_finite($number) ? $number : null;
    }

    private function fallback(float|int $value): string
    {
        $decimals = floor((float) $value) === (float) $value ? 0 : 4;
        if ($decimals === 0) {
            return number_format((float) $value, 0, ',', '.');
        }

        return rtrim(rtrim(number_format((float) $value, $decimals, ',', '.'), '0'), ',');
    }
}
