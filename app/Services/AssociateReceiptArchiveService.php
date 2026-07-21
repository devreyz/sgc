<?php

namespace App\Services;

use App\Enums\DeliveryStatus;
use App\Models\AssociateReceipt;
use App\Models\ProductionDelivery;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use RuntimeException;

class AssociateReceiptArchiveService
{
    public function __construct(private readonly TenantGoogleDriveService $drive)
    {
    }

    public function sync(AssociateReceipt $receipt): void
    {
        $receipt->loadMissing(['tenant', 'project', 'associate.user', 'payments.bankAccount']);

        if (! $receipt->tenant || ! $receipt->project || ! $receipt->associate) {
            throw new RuntimeException('O comprovante nao possui tenant, projeto ou associado valido.');
        }

        $ids = collect($receipt->delivery_ids ?? [])->map(fn ($id) => (int) $id)->filter()->unique();
        $query = ProductionDelivery::query()
            ->where('tenant_id', $receipt->tenant_id)
            ->where('sales_project_id', $receipt->sales_project_id)
            ->where('associate_id', $receipt->associate_id)
            ->where('status', DeliveryStatus::APPROVED)
            ->whereNotNull('parent_delivery_id')
            ->with(['product', 'customer', 'parentDelivery'])
            ->orderBy('delivery_date');

        $ids->isNotEmpty()
            ? $query->whereIn('id', $ids->all())
            : $query->where('associate_receipt_id', $receipt->id);

        $distributions = $query->get();
        if ($distributions->isEmpty()) {
            throw new RuntimeException('O comprovante nao possui distribuicoes financeiras validas.');
        }

        $data = ReceiptDataBuilder::fromDeliveries($distributions, null, $receipt->project);
        $pdf = Pdf::loadView('pdf.project-associate-receipt', [
            'tenant' => $receipt->tenant,
            'project' => $receipt->project,
            'associate' => $receipt->associate,
            'receipt' => $receipt,
            'summary' => $data['summary'],
            'productsSummary' => $data['productsSummary'],
            'hasRoundingDivergence' => $data['hasRoundingDivergence'],
            'feeBreakdown' => $data['feeBreakdown'],
        ])->setPaper('a4', 'portrait');

        $label = str_replace('/', '-', $receipt->formatted_number);
        $associate = Str::slug($receipt->associate->display_name ?: 'associado');
        $projectFolder = $receipt->project->driveFolderName();
        $folders = ['Comprovantes', 'Associados', (string) $receipt->receipt_year, $projectFolder];

        $this->drive->putDocument(
            $receipt->tenant,
            $receipt,
            'associate_receipt',
            $folders,
            "comprovante-{$label}-{$associate}.pdf",
            $pdf->output(),
        );

        if ($receipt->payments->isNotEmpty() || (float) $receipt->amount_paid > 0) {
            $paymentPdf = Pdf::loadView('pdf.associate-receipt-payments', [
                'tenant' => $receipt->tenant,
                'project' => $receipt->project,
                'associate' => $receipt->associate,
                'receipt' => $receipt,
                'payments' => $receipt->payments,
            ])->setPaper('a4', 'portrait');

            $this->drive->putDocument(
                $receipt->tenant,
                $receipt,
                'associate_receipt_payments',
                ['Comprovantes', 'Pagamentos', (string) $receipt->receipt_year, $projectFolder],
                "pagamentos-{$label}-{$associate}.pdf",
                $paymentPdf->output(),
            );
        }
    }
}
