<?php

namespace App\Services\Planning;

final class QuantityMassConverter
{
    public function factor(?string $unit, mixed $explicit = null): ?string
    {
        $factor = match (mb_strtolower(trim($unit ?? ''))) {
            'kg', 'quilo', 'quilograma' => '1000',
            'g', 'grama' => '1',
            default => $explicit,
        };
        return $factor !== null && bccomp((string) $factor, '0', 16) > 0 ? (string) $factor : null;
    }
}
