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
        $this->stampPageNumbers($canvas, $font);

        return $pdf;
    }

    public function paginate(PDF $pdf, bool $alreadyRendered = false): PDF
    {
        if (! $alreadyRendered) $pdf->render();
        $dompdf = $pdf->getDomPDF();
        $this->stampPageNumbers($dompdf->getCanvas(), $dompdf->getFontMetrics()->getFont('DejaVu Sans', 'normal'));
        return $pdf;
    }

    private function stampPageNumbers(\Dompdf\Canvas $canvas, mixed $font): void
    {
        $canvas->page_script(function (int $page, int $total, \Dompdf\Canvas $pageCanvas) use ($font): void {
            $label = 'Página '.$page.' de '.$total;
            $width = $pageCanvas->get_text_width($label, $font, 7);
            $pageCanvas->text($pageCanvas->get_width() - 43 - $width, $pageCanvas->get_height() - 24,
                $label, $font, 7, [0.40, 0.44, 0.46]);
        });
    }
}
