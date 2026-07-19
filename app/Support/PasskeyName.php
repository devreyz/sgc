<?php

namespace App\Support;

use InvalidArgumentException;

class PasskeyName
{
    public const MAX_LENGTH = 60;

    public const MAX_WORDS = 3;

    public static function normalize(?string $name): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', (string) $name));
    }

    public static function validate(string $name): string
    {
        $name = self::normalize($name);

        if ($name === '' || mb_strlen($name) > self::MAX_LENGTH || count(self::words($name)) > self::MAX_WORDS) {
            throw new InvalidArgumentException('O nome da chave deve ter no maximo tres palavras.');
        }

        return $name;
    }

    public static function suggest(?string $name): string
    {
        $words = array_slice(self::words(self::normalize($name)), 0, self::MAX_WORDS);
        $suggestion = mb_substr(implode(' ', $words), 0, self::MAX_LENGTH);

        return $suggestion !== '' ? $suggestion : 'Meu dispositivo';
    }

    private static function words(string $name): array
    {
        return array_values(array_filter(preg_split('/\s+/u', $name) ?: []));
    }
}
