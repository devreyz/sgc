<?php

namespace App\Http\Controllers;

use App\Models\FinancialReceipt;
use App\Services\NumberInWordsService;
use App\Services\TemplatedPdfService;
use App\Services\TenantIdentityService;
use Illuminate\Http\Response;

class FinancialReceiptController extends Controller
{
    public function print(FinancialReceipt $financialReceipt, NumberInWordsService $numbers, TenantIdentityService $identity, TemplatedPdfService $pdfService): Response
    {
        $this->authorize('view', $financialReceipt);
        abort_if($financialReceipt->isDraft(), 409, 'Emita o recibo antes de imprimir.');

        $financialReceipt->load(['tenant', 'items', 'bankAccount', 'chartAccount', 'issuer', 'canceller']);
        $pdf = $pdfService->generateSystemPdf('pdf.financial-receipt', [
            'receipt' => $financialReceipt,
            'tenant' => $financialReceipt->tenant,
            'amountInWords' => $numbers->money($financialReceipt->total_amount),
            'receiverName' => $identity->displayName($financialReceipt->tenant_id, $financialReceipt->issued_by),
        ], $pdfService->systemPdfOptions(
            'pdf.financial-receipt',
            'Recibo de Recebimento',
            null,
            (int) $financialReceipt->tenant_id,
        ));

        return $pdf->stream(str_replace('/', '-', strtolower($financialReceipt->formatted_number)).'.pdf');
    }
}
