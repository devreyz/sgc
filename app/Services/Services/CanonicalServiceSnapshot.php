<?php

namespace App\Services\Services;

final class CanonicalServiceSnapshot
{
    public function normalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (array_is_list($value)) {
            return array_map(fn ($item) => $this->normalize($item), $value);
        }
        ksort($value);

        return array_map(fn ($item) => $this->normalize($item), $value);
    }

    public function hash(array $snapshot): string
    {
        return hash('sha256', json_encode($this->normalize($snapshot), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }
}
