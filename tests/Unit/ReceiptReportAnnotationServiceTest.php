<?php

namespace Tests\Unit;

use App\Models\AssociateReceipt;
use App\Services\ReceiptReportAnnotationService;
use PHPUnit\Framework\TestCase;

class ReceiptReportAnnotationServiceTest extends TestCase
{
    public function test_product_distribution_and_global_notes_have_stable_markers(): void
    {
        $receipt = new AssociateReceipt;
        $receipt->report_annotations = [
            ['target' => 'global', 'text' => 'Conferido no relatório.'],
            ['target' => 'product:7', 'text' => 'Milho substituído.'],
            ['target' => 'distribution:19', 'text' => 'Entrega ajustada.'],
        ];

        $service = new ReceiptReportAnnotationService;
        $notes = $service->notes($receipt);

        self::assertNull($notes[0]['marker']);
        self::assertSame('¹', $notes[1]['marker']);
        self::assertSame('²', $notes[2]['marker']);
        self::assertSame('¹²', $service->markers($notes, 7, [19]));
        self::assertSame('²', $service->markers($notes, 8, [19]));
        self::assertSame('', $service->markers($notes, 8, [20]));
    }
}
