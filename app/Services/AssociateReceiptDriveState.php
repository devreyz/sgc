<?php

namespace App\Services;

use App\Enums\DeliveryStatus;
use App\Models\AssociateReceipt;
use App\Models\CloudDocument;
use App\Models\ProductionDelivery;

class AssociateReceiptDriveState
{
    public function fingerprint(AssociateReceipt $receipt): string
    {
        $deliveryIds = collect($receipt->delivery_ids ?? [])
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->sort()
            ->values()
            ->all();

        return hash('sha256', json_encode([
            'receipt_id' => (int) $receipt->id,
            'updated_at' => $receipt->updated_at?->format('Y-m-d H:i:s.u'),
            'status' => $receipt->status?->value,
            'delivery_ids' => $deliveryIds,
            'totals' => [
                (string) $receipt->total_gross,
                (string) $receipt->total_fees,
                (string) $receipt->total_net,
                (string) $receipt->amount_paid,
            ],
            'fee_snapshot' => $receipt->fee_snapshot,
            'issued_at' => $receipt->issued_at?->format('Y-m-d'),
            'notes' => $receipt->notes,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    public function eligible(AssociateReceipt $receipt): bool
    {
        if (! $receipt->tenant_id || ! $receipt->sales_project_id || ! $receipt->associate_id
            || empty($receipt->delivery_ids) || (float) ($receipt->total_net ?? 0) <= 0) {
            return false;
        }

        $ids = collect($receipt->delivery_ids)->map(fn ($id): int => (int) $id)->filter()->unique();

        return ProductionDelivery::query()
            ->where('tenant_id', $receipt->tenant_id)
            ->where('sales_project_id', $receipt->sales_project_id)
            ->where('associate_id', $receipt->associate_id)
            ->where('status', DeliveryStatus::APPROVED->value)
            ->whereNotNull('parent_delivery_id')
            ->where(function ($query) use ($ids, $receipt): void {
                $query->where('associate_receipt_id', $receipt->id);
                if ($ids->isNotEmpty()) {
                    $query->orWhereIn('id', $ids->all());
                }
            })
            ->exists();
    }

    public function document(AssociateReceipt $receipt): ?CloudDocument
    {
        return CloudDocument::query()
            ->where('tenant_id', $receipt->tenant_id)
            ->where('provider', 'google_drive')
            ->where('document_type', 'associate_receipt')
            ->where('documentable_type', $receipt->getMorphClass())
            ->where('documentable_id', $receipt->id)
            ->first();
    }

    public function alreadyHandled(AssociateReceipt $receipt, string $fingerprint): bool
    {
        $document = $this->document($receipt);

        return $document !== null
            && in_array($document->status, ['synced', 'rejected'], true)
            && data_get($document->metadata, 'source_fingerprint') === $fingerprint;
    }

    public function recordRejected(AssociateReceipt $receipt, string $fingerprint, string $reason): void
    {
        $document = $this->document($receipt) ?? new CloudDocument();
        if (! $document->exists) {
            $document->forceFill([
                'tenant_id' => $receipt->tenant_id,
                'provider' => 'google_drive',
                'document_type' => 'associate_receipt',
                'documentable_type' => $receipt->getMorphClass(),
                'documentable_id' => $receipt->id,
                'remote_path' => 'Comprovantes/aguardando-dados/comprovante-'.$receipt->id.'.pdf',
            ]);
        }

        $document->forceFill([
            'status' => 'rejected',
            'last_error' => $reason,
            'metadata' => array_merge($document->metadata ?? [], [
                'source_fingerprint' => $fingerprint,
                'permanent_until_changed' => true,
            ]),
        ])->save();
    }

    public function recordSynced(AssociateReceipt $receipt, string $fingerprint): void
    {
        $document = $this->document($receipt);
        if (! $document) {
            return;
        }

        $document->forceFill([
            'metadata' => array_merge($document->metadata ?? [], [
                'source_fingerprint' => $fingerprint,
                'permanent_until_changed' => false,
            ]),
        ])->save();
    }
}
