<?php

namespace App\Support;

/** Display only; never use formatted quantities in financial calculations. */
final class PdfQuantityFormatter
{
    public static function format(int|float|string|null $quantity): string
    {
        return number_format((float) ($quantity ?? 0), 3, ',', '.');
    }
}
