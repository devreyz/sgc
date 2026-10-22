<?php

namespace Tests\Unit;

use App\Support\PdfQuantityFormatter;
use PHPUnit\Framework\TestCase;

class PdfQuantityFormatterTest extends TestCase
{
    public function test_quantities_always_have_three_decimal_places_without_changing_the_original_value(): void
    {
        foreach ([['23.347', '23,347'], ['6.9', '6,900'], ['13.1', '13,100'], ['34', '34,000'],
            ['0', '0,000'], [null, '0,000'], ['1234.5', '1.234,500'], ['1.2345', '1,235']] as [$value, $expected]) {
            $original = $value;
            self::assertSame($expected, PdfQuantityFormatter::format($value));
            self::assertSame($original, $value);
        }
    }
}
