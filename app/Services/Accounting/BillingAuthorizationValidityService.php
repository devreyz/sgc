<?php

namespace App\Services\Accounting;

use App\Enums\BillingAuthorizationStatus;
use App\Enums\CustomerReceiptStatus;
use App\Models\BillingAuthorization;
use App\Models\CustomerBillingReceipt;
use App\Models\ProductionDelivery;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class BillingAuthorizationValidityService
{
    public function __construct(
        private readonly BillingAuthorizationSnapshotService $snapshots,
        private readonly BillingAuthorizationNotificationService $notifications,
        private readonly AccountingProcessIntegrityService $integrity,
    ) {}

    public function currentHash(CustomerBillingReceipt $receipt, bool $lock = false): string
    {
        $fresh = CustomerBillingReceipt::withoutGlobalScopes()
            ->where('tenant_id', $receipt->tenant_id)
            ->findOrFail($receipt->id);
        $query = ProductionDelivery::withoutGlobalScopes()
            ->where('tenant_id', $fresh->tenant_id)
            ->where('billing_receipt_id', $fresh->id)
            ->whereNotNull('parent_delivery_id')
            ->orderBy('id');
        if ($lock) {
            $query->lockForUpdate();
        }
        $distributions = $query->get();

        return $this->snapshots->hash($this->snapshots->build($fresh, $distributions));
    }

    public function isValid(CustomerBillingReceipt $receipt, ?BillingAuthorization $authorization = null): bool
    {
        $authorization ??= BillingAuthorization::withoutGlobalScopes()
            ->where('tenant_id', $receipt->tenant_id)
            ->where('customer_billing_receipt_id', $receipt->id)
            ->where('active_marker', true)
            ->latest('sequence')->first();

        if (! $authorization
            || $authorization->status !== BillingAuthorizationStatus::AUTHORIZED
            || ! $authorization->active_marker
            || (int) $authorization->tenant_id !== (int) $receipt->tenant_id
            || (int) $authorization->customer_billing_receipt_id !== (int) $receipt->id
            || ! $this->hasCompatibleFinancialStatus($receipt)
            || $this->integrity->inspect($receipt)['critical_count'] > 0) {
            return false;
        }

        try {
            return hash_equals((string) $authorization->snapshot_hash, $this->currentHash($receipt));
        } catch (\Throwable) {
            return false;
        }
    }

    public function invalidateIfChanged(CustomerBillingReceipt $receipt, ?User $actor = null, string $reason = 'A cobrança foi alterada após o envio.'): ?BillingAuthorization
    {
        $receiptId = (int) $receipt->id;
        $tenantId = (int) $receipt->tenant_id;
        $result = DB::transaction(function () use ($receiptId, $tenantId, $actor, $reason): ?BillingAuthorization {
            $lockedReceipt = CustomerBillingReceipt::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->lockForUpdate()->find($receiptId);
            if (! $lockedReceipt) {
                return null;
            }

            $authorization = BillingAuthorization::withoutGlobalScopes()
                ->where('tenant_id', $lockedReceipt->tenant_id)
                ->where('customer_billing_receipt_id', $lockedReceipt->id)
                ->where('active_marker', true)
                ->whereIn('status', [BillingAuthorizationStatus::SENT->value, BillingAuthorizationStatus::AUTHORIZED->value])
                ->lockForUpdate()->latest('sequence')->first();
            if (! $authorization) {
                return null;
            }

            $currentHash = null;
            $invalidReason = $reason;
            try {
                $currentHash = $this->currentHash($lockedReceipt, true);
            } catch (\Throwable $exception) {
                $invalidReason = $reason.' Não foi possível reconstruir a versão material atual.';
            }
            $stateInvalid = ! $this->hasCompatibleFinancialStatus($lockedReceipt);
            $integrityInvalid = $this->integrity->inspect($lockedReceipt)['critical_count'] > 0;
            if (! $stateInvalid && ! $integrityInvalid && $currentHash !== null
                && hash_equals((string) $authorization->snapshot_hash, $currentHash)) {
                return null;
            }

            if ($stateInvalid) {
                $invalidReason = 'A situação financeira da cobrança deixou de permitir esta autorização.';
            } elseif ($integrityInvalid) {
                $invalidReason = 'A cobrança passou a possuir uma inconsistência financeira crítica.';
            }

            $authorization->forceFill([
                'status' => BillingAuthorizationStatus::INVALIDATED,
                'active_marker' => null,
                'current_hash' => $currentHash,
                'invalidated_at' => now(),
                'invalidated_by' => $actor?->id,
                'invalidation_reason' => $invalidReason,
            ])->save();

            $log = activity()->performedOn($authorization)->withProperties([
                'tenant_id' => $authorization->tenant_id,
                'receipt_id' => $lockedReceipt->id,
                'sequence' => $authorization->sequence,
                'snapshot_hash' => $authorization->snapshot_hash,
                'current_hash' => $currentHash,
            ]);
            if ($actor) {
                $log->causedBy($actor);
            }
            $log->log('Autorização da organização invalidada');

            return $authorization;
        }, 5);

        if ($result) {
            DB::afterCommit(fn () => $this->notifications->invalidated($result->fresh(['receipt.tenant'])));
        }

        return $result;
    }

    public function hasCompatibleFinancialStatus(CustomerBillingReceipt $receipt): bool
    {
        return in_array($receipt->status, [
            CustomerReceiptStatus::PENDING_PAYMENT,
            CustomerReceiptStatus::PARTIALLY_PAID,
            CustomerReceiptStatus::PAID,
        ], true);
    }
}
