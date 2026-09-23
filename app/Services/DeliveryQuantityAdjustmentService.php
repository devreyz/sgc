<?php

namespace App\Services;

use App\Enums\DeliveryStatus;
use App\Models\ProductionDelivery;
use App\Models\ProductionDeliveryQuantityAdjustment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeliveryQuantityAdjustmentService
{
    public function adjust(
        ProductionDelivery $delivery,
        float $quantity,
        ?string $reason,
        User $actor,
    ): ProductionDeliveryQuantityAdjustment {
        if ($delivery->parent_delivery_id !== null) {
            throw ValidationException::withMessages([
                'delivery' => 'A devolução deve ser registrada na entrega original, não em uma distribuição.',
            ]);
        }

        return DB::transaction(function () use ($delivery, $quantity, $reason, $actor): ProductionDeliveryQuantityAdjustment {
            $locked = ProductionDelivery::query()
                ->where('tenant_id', $delivery->tenant_id)
                ->whereNull('parent_delivery_id')
                ->lockForUpdate()
                ->findOrFail($delivery->id);

            if (! in_array($locked->status, [DeliveryStatus::PENDING, DeliveryStatus::APPROVED], true)) {
                throw ValidationException::withMessages([
                    'delivery' => 'Somente entregas pendentes ou aprovadas podem ser rejeitadas ou devolvidas.',
                ]);
            }

            $distributed = (float) ProductionDelivery::query()
                ->where('tenant_id', $locked->tenant_id)
                ->where('parent_delivery_id', $locked->id)
                ->whereNotIn('status', [DeliveryStatus::REJECTED->value, DeliveryStatus::CANCELLED->value])
                ->sum('quantity');
            $before = (float) $locked->quantity;
            $available = max(0.0, $before - $distributed);

            if ($quantity <= 0 || $quantity > $available + 0.00005) {
                throw ValidationException::withMessages([
                    'quantity' => 'A quantidade deve ser maior que zero e não pode ultrapassar o saldo sem distribuição ('.number_format($available, 4, ',', '.').').',
                ]);
            }

            $kind = $locked->status === DeliveryStatus::PENDING ? 'rejection' : 'return';
            $after = max($distributed, $before - $quantity);
            $isFullyRemoved = $after <= 0.00005 && $distributed <= 0.00005;
            $nextStatus = $isFullyRemoved ? DeliveryStatus::REJECTED : DeliveryStatus::APPROVED;

            $locked->forceFill([
                'original_quantity' => $locked->original_quantity ?: $before,
                'quantity' => $after,
                'status' => $nextStatus,
                'approved_by' => $nextStatus === DeliveryStatus::APPROVED ? $actor->id : $locked->approved_by,
                'approved_at' => $nextStatus === DeliveryStatus::APPROVED ? ($locked->approved_at ?: now()) : $locked->approved_at,
            ])->save();

            return ProductionDeliveryQuantityAdjustment::query()->create([
                'tenant_id' => $locked->tenant_id,
                'production_delivery_id' => $locked->id,
                'kind' => $kind,
                'quantity' => $quantity,
                'quantity_before' => $before,
                'quantity_after' => $after,
                'reason' => trim((string) $reason) ?: ($kind === 'rejection'
                    ? 'Rejeição registrada durante a conferência da entrega.'
                    : 'Devolução registrada após a aprovação da entrega.'),
                'created_by' => $actor->id,
            ]);
        });
    }
}
