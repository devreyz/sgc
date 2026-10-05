<?php

namespace App\Services;

use App\Enums\BillingStatus;
use App\Enums\DeliveryStatus;
use App\Models\AssociateReceipt;
use App\Models\CustomerBillingReceipt;
use App\Models\ProductionDelivery;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class DeletedDistributionService
{
    private const QUANTITY_TOLERANCE = 0.0005;

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

    /** @return array{allowed: bool, reason: string, available_quantity?: float} */
    public function restorationStatus(ProductionDelivery $distribution): array
    {
        if (! $distribution->trashed() || $distribution->parent_delivery_id === null) {
            return $this->blocked('Somente distribuições presentes no histórico podem ser restauradas.');
        }

        $parent = ProductionDelivery::withoutGlobalScopes()->find($distribution->parent_delivery_id);
        if (! $parent
            || (int) $parent->tenant_id !== (int) $distribution->tenant_id
            || (int) $parent->sales_project_id !== (int) $distribution->sales_project_id
            || (int) $parent->associate_id !== (int) $distribution->associate_id
            || (int) $parent->product_id !== (int) $distribution->product_id
            || $parent->parent_delivery_id !== null) {
            return $this->blocked('A entrega-pai não está ativa ou não corresponde à distribuição arquivada.');
        }

        $allocated = (float) ProductionDelivery::withoutGlobalScopes()
            ->where('tenant_id', $distribution->tenant_id)
            ->where('parent_delivery_id', $parent->id)
            ->whereNull('deleted_at')
            ->whereNotIn('status', [DeliveryStatus::REJECTED->value, DeliveryStatus::CANCELLED->value])
            ->sum('quantity');
        $available = max(0, (float) $parent->quantity - $allocated);
        if ((float) $distribution->quantity > $available + self::QUANTITY_TOLERANCE) {
            return [
                'allowed' => false,
                'reason' => sprintf(
                    'A restauração ultrapassaria o recebimento. Quantidade arquivada: %s | saldo disponível: %s.',
                    number_format((float) $distribution->quantity, 3, ',', '.'),
                    number_format($available, 3, ',', '.'),
                ),
                'available_quantity' => $available,
            ];
        }

        return [
            'allowed' => true,
            'reason' => sprintf(
                'A distribuição usa %s de %s disponíveis na entrega-pai.',
                number_format((float) $distribution->quantity, 3, ',', '.'),
                number_format($available, 3, ',', '.'),
            ),
            'available_quantity' => $available,
        ];
    }

    public function restore(ProductionDelivery $distribution, User $actor): ProductionDelivery
    {
        return DB::transaction(function () use ($distribution, $actor): ProductionDelivery {
            $locked = ProductionDelivery::withoutGlobalScopes()->withTrashed()
                ->where('tenant_id', $distribution->tenant_id)
                ->whereNotNull('parent_delivery_id')
                ->lockForUpdate()
                ->findOrFail($distribution->id);
            $parent = ProductionDelivery::withoutGlobalScopes()
                ->where('tenant_id', $locked->tenant_id)
                ->lockForUpdate()
                ->find($locked->parent_delivery_id);

            // Bloqueia também as distribuições irmãs para impedir duas
            // restaurações simultâneas de consumirem o mesmo saldo físico.
            ProductionDelivery::withoutGlobalScopes()->withTrashed()
                ->where('tenant_id', $locked->tenant_id)
                ->where('parent_delivery_id', $locked->parent_delivery_id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id']);

            $status = $this->restorationStatus($locked);
            if (! $status['allowed']) {
                throw ValidationException::withMessages(['distribution' => $status['reason']]);
            }

            if (! $parent || in_array($parent->status, [DeliveryStatus::REJECTED, DeliveryStatus::CANCELLED], true)) {
                throw ValidationException::withMessages([
                    'distribution' => 'A entrega-pai não está em situação válida para receber uma distribuição restaurada.',
                ]);
            }

            $project = $parent->salesProject()->firstOrFail();
            $associate = $parent->associate()->firstOrFail();
            app(AssociateProjectLimitService::class)->assertContext($project, $associate);
            app(ProjectDistributionCustomerService::class)->assertAllowed($project, [(int) $locked->customer_id]);
            app(AssociateProjectLimitService::class)->validateDistribution(
                $project,
                $associate,
                (float) $locked->quantity * (float) $locked->unit_price,
                (int) $locked->id,
            );

            ProductionDelivery::withoutGlobalScopes()->withTrashed()
                ->whereKey($locked->id)
                ->update(['deleted_at' => null, 'updated_at' => now()]);

            activity('delivery_integrity')->performedOn($locked)->causedBy($actor)->withProperties([
                'tenant_id' => (int) $locked->tenant_id,
                'distribution_id' => (int) $locked->id,
                'parent_delivery_id' => (int) $locked->parent_delivery_id,
                'sales_project_id' => (int) $locked->sales_project_id,
                'quantity' => (string) $locked->quantity,
                'available_before_restore' => (string) ($status['available_quantity'] ?? ''),
                'action' => 'manual_restore_deleted_distribution',
            ])->log('Distribuição restaurada manualmente após validação dos limites');

            return ProductionDelivery::withoutGlobalScopes()->findOrFail($locked->id);
        }, 5);
    }

    /** @return array{allowed: false, reason: string} */
    private function blocked(string $reason): array
    {
        return ['allowed' => false, 'reason' => $reason];
    }
}
