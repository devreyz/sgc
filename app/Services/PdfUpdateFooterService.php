<?php

namespace App\Services;

use Barryvdh\DomPDF\PDF;
use Illuminate\Support\Carbon;

final class PdfUpdateFooterService
{
    /** Stamp the actual rendering time, not the receipt's original issue date. */
    public function apply(PDF $pdf, ?Carbon $renderedAt = null, bool $alreadyRendered = false): PDF
    {
        $renderedAt ??= now();
        $label = 'Gerado em '.$renderedAt->copy()->timezone('America/Sao_Paulo')->format('d/m/Y H:i:s').' (Brasília)';
        if (! $alreadyRendered) $pdf->render();
        $dompdf = $pdf->getDomPDF();
        $canvas = $dompdf->getCanvas();
        $font = $dompdf->getFontMetrics()->getFont('DejaVu Sans', 'normal');
        $canvas->page_text(43, $canvas->get_height() - 24, $label, $font, 7, [0.40, 0.44, 0.46]);

        return $pdf;
    }
}
