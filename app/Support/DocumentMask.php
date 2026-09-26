<?php

namespace App\Support;

final class DocumentMask
{
    /** Mascara documentos pessoais para exibicao; nunca use para persistencia. */
    public static function forDisplay(mixed $document): string
    {
        $digits = preg_replace('/\D+/', '', (string) ($document ?? '')) ?? '';

        return match (strlen($digits)) {
            11 => '***.'.substr($digits, 3, 3).'.'.substr($digits, 6, 3).'-**',
            14 => '**.***.***/'.substr($digits, 8, 4).'-**',
            default => $digits === '' ? '—' : str_repeat('*', max(4, min(12, strlen($digits)))),
        };
    }
}
