<?php

namespace App\Services;

use App\Enums\BillingStatus;
use App\Models\AssociateReceipt;
use App\Models\CustomerBillingReceipt;
use App\Models\ProductionDelivery;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class DeletedDistributionService
{
    /** @return array{allowed: bool, reason: string} */
    public function permanentDeletionStatus(ProductionDelivery $distribution): array
    {
        if (! $distribution->trashed() || $distribution->parent_delivery_id === null) {
            return $this->blocked('Somente distribuições já removidas podem ser excluídas permanentemente.');
        }

        if ($distribution->paid
            || $distribution->billing_status !== BillingStatus::UNBILLED
            || $distribution->distribution_billing_id
            || $distribution->project_payment_id) {
            return $this->blocked('A distribuição possui faturamento ou pagamento e deve permanecer no histórico.');
        }

        if (Schema::hasTable('project_payments') && DB::table('project_payments')
            ->where('production_delivery_id', $distribution->id)->exists()) {
            return $this->blocked('A distribuição está vinculada a um pagamento e deve permanecer no histórico.');
        }

        $associateReceipts = AssociateReceipt::withoutGlobalScopes()
            ->where('tenant_id', $distribution->tenant_id)
            ->where(function ($query) use ($distribution): void {
                $query->where('id', $distribution->associate_receipt_id ?: 0)
                    ->orWhereJsonContains('delivery_ids', $distribution->id)
                    ->orWhereJsonContains('delivery_ids', (string) $distribution->id);
            })->get();
        foreach ($associateReceipts as $receipt) {
            if ($receipt->hasFinancialLocks() || in_array($distribution->id, array_map('intval', $receipt->delivery_ids ?? []), true)) {
                return $this->blocked('A distribuição consta em um comprovante do membro e deve permanecer auditável.');
            }
        }

        $customerReceipts = CustomerBillingReceipt::withoutGlobalScopes()
            ->where('tenant_id', $distribution->tenant_id)
            ->where(function ($query) use ($distribution): void {
                $query->where('id', $distribution->billing_receipt_id ?: 0)
                    ->orWhereJsonContains('delivery_ids', $distribution->id)
                    ->orWhereJsonContains('delivery_ids', (string) $distribution->id);
            })->get();
        foreach ($customerReceipts as $receipt) {
            if ((float) ($receipt->amount_paid ?? 0) > 0
                || $receipt->status?->isLocked()
                || in_array($distribution->id, array_map('intval', $receipt->delivery_ids ?? []), true)) {
                return $this->blocked('A distribuição consta em uma cobrança do cliente e deve permanecer auditável.');
            }
        }

        return ['allowed' => true, 'reason' => 'Sem comprovante, faturamento ou pagamento.'];
    }

    public function forceDelete(ProductionDelivery $distribution, User $actor): void
    {
        DB::transaction(function () use ($distribution, $actor): void {
            $locked = ProductionDelivery::withoutGlobalScopes()->withTrashed()
                ->where('tenant_id', $distribution->tenant_id)
                ->whereNotNull('parent_delivery_id')
                ->lockForUpdate()
                ->findOrFail($distribution->id);
            $status = $this->permanentDeletionStatus($locked);
            if (! $status['allowed']) {
                throw ValidationException::withMessages(['distribution' => $status['reason']]);
            }

            activity('delivery_integrity')->causedBy($actor)->withProperties([
                'tenant_id' => (int) $locked->tenant_id,
                'distribution_id' => (int) $locked->id,
                'parent_delivery_id' => (int) $locked->parent_delivery_id,
                'sales_project_id' => (int) $locked->sales_project_id,
            ])->log('Distribuição removida permanentemente após validação de integridade');

            $locked->forceDelete();
        }, 5);
    }

    /** @return array{allowed: false, reason: string} */
    private function blocked(string $reason): array
    {
        return ['allowed' => false, 'reason' => $reason];
    }
}
