<?php

namespace Tests\Unit;

use App\Support\DocumentMask;
use PHPUnit\Framework\TestCase;

class DocumentMaskTest extends TestCase
{
    public function test_cpf_is_never_returned_in_full_for_display(): void
    {
        $masked = DocumentMask::forDisplay('123.456.789-00');

        self::assertSame('***.456.789-**', $masked);
        self::assertStringNotContainsString('123', $masked);
        self::assertStringNotContainsString('00', $masked);
    }

    public function test_unknown_document_is_fully_obscured(): void
    {
        self::assertSame('****', DocumentMask::forDisplay('123'));
        self::assertSame('—', DocumentMask::forDisplay(null));
    }
}
