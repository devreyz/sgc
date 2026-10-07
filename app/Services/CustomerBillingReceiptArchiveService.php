<?php

namespace App\Services;

use App\Filament\Resources\CustomerBillingReceiptResource;
use App\Models\CustomerBillingReceipt;
use App\Models\ProductionDelivery;
use Illuminate\Support\Str;
use RuntimeException;

class CustomerBillingReceiptArchiveService
{
    public function __construct(private readonly TenantGoogleDriveService $drive) {}

    public function sync(CustomerBillingReceipt $receipt): void
    {
        $receipt->loadMissing(['tenant', 'project', 'projects', 'customer', 'organization']);

        if (! $receipt->tenant || ! $receipt->project || (! $receipt->customer && ! $receipt->organization)) {
            throw new RuntimeException('O comprovante de faturamento está incompleto.');
        }

        $deliveryIds = collect($receipt->delivery_ids ?? [])
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->unique()
            ->values();
        $projects = $receipt->includedProjects();
        $projectIds = $projects->pluck('id')->map(fn ($id): int => (int) $id)->all();

        if ($deliveryIds->isEmpty() || $projectIds === []) {
            throw new RuntimeException('O comprovante de faturamento não possui distribuições.');
        }

        $distributions = ProductionDelivery::withoutGlobalScopes()
            ->where('tenant_id', $receipt->tenant_id)
            ->whereNull('deleted_at')
            ->whereIn('sales_project_id', $projectIds)
            ->whereNotNull('parent_delivery_id')
            ->whereIn('id', $deliveryIds->all())
            ->with(['product', 'customer.priceTable'])
            ->orderBy('delivery_date')
            ->get();

        if ($distributions->count() !== $deliveryIds->count()) {
            throw new RuntimeException('Uma ou mais distribuições do comprovante não estão mais disponíveis.');
        }

        $organizationReport = $receipt->organization && ! $receipt->customer;
        $view = $organizationReport
            ? 'pdf.customer-organization-receipt'
            : 'pdf.customer-billing-receipt';
        $columns = ['unit_price', 'gross'];
        $data = $organizationReport
            ? CustomerBillingReceiptResource::buildOrganizationReportData(
                $distributions, $receipt, $receipt->tenant, $receipt->project,
                $receipt->organization, $projects, $columns,
            )
            : CustomerBillingReceiptResource::buildCustomerReceiptData(
                $distributions, $receipt, $receipt->tenant, $receipt->project,
                $receipt->customer, $projects, $columns,
            );
        $data['table_scale'] = 100;

        $pdf = app(TemplatedPdfService::class)->generateSystemPdf($view, $data, [
            'paper' => 'a4',
            'orientation' => 'portrait',
        ]);

        $label = str_replace('/', '-', $receipt->formatted_number);
        $recipient = Str::slug($receipt->recipient_name ?: 'destinatario');
        $month = $receipt->issued_at?->format('m') ?: now()->format('m');

        $this->drive->putDocument(
            $receipt->tenant,
            $receipt,
            'customer_billing_receipt',
            ['Comprovantes de faturamento', (string) $receipt->receipt_year, $month, $receipt->project->driveFolderName()],
            "comprovante-{$label}-{$recipient}.pdf",
            $pdf->output(),
        );
    }
}
