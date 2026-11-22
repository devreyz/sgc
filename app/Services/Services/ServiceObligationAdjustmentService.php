<?php

namespace App\Services\Services;

use App\Models\ServiceObligation;
use App\Models\ServiceObligationAdjustment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ServiceObligationAdjustmentService
{
    public function changeProviderRate(ServiceObligation $obligation, float $rate, float $expectedTotal, string $reason, string $operationKey, User $actor): ServiceObligationAdjustment
    {
        return DB::transaction(function () use ($obligation, $rate, $expectedTotal, $reason, $operationKey, $actor) {
            $locked = ServiceObligation::query()->where('tenant_id', $obligation->tenant_id)->whereKey($obligation->id)->lockForUpdate()->firstOrFail();
            $existing = $locked->adjustments()->where('operation_key', $operationKey)->first();
            if ($existing) {
                if ((float) data_get($existing->metadata, 'new_rate') !== $rate || $existing->reason !== $reason) {
                    throw ValidationException::withMessages(['operation_key' => 'Esta operação já foi usada com outros dados.']);
                }
                return $existing;
            }
            if ($locked->direction !== 'payable' || $locked->status === 'cancelled' || $locked->activePaymentPlan()
                || \App\Models\ServicePayoutRequestItem::query()->where('service_obligation_id', $locked->id)->whereHas('request', fn ($query) => $query->where('tenant_id', $locked->tenant_id)->whereIn('status', ['pending', 'approved']))->exists()
                || ! $locked->execution?->order || $locked->execution->order->operational_status === 'cancelled') {
                throw ValidationException::withMessages(['provider_rate' => 'Esta ordem não permite alterar o valor do prestador.']);
            }
            if (abs($locked->total_amount - $expectedTotal) > .001) {
                throw ValidationException::withMessages(['provider_rate' => 'O valor mudou. Reabra a ordem antes de reajustar.']);
            }
            $base = collect(data_get($locked->composition_snapshot, 'lines', []))->first(fn ($line) => ($line['type'] ?? null) === 'base' && ($line['source_key'] ?? null) === 'provider_compensation');
            $method = data_get($base, 'rule_snapshot.method');
            if (! $base || ! in_array($method, ['fixed', 'quantity_x_rate'], true) || ! is_finite($rate) || $rate <= 0) {
                throw ValidationException::withMessages(['provider_rate' => 'A remuneração desta ordem não usa uma tarifa fixa ou por quantidade.']);
            }
            $quantity = $method === 'fixed' ? 1.0 : (float) ($base['quantity'] ?? 0);
            if ($quantity <= 0) {
                throw ValidationException::withMessages(['provider_rate' => 'Confira a quantidade executada antes de reajustar.']);
            }
            $previous = $locked->latestProviderRateAdjustment()->first();
            $oldBase = $previous ? (float) data_get($previous->metadata, 'new_base') : (float) $base['amount'];
            $newBase = round($quantity * $rate, 2);
            if ($newBase <= 0 || $newBase > 999999999999.99) {
                throw ValidationException::withMessages(['provider_rate' => 'O total calculado está fora do limite permitido. Confira a quantidade e a tarifa.']);
            }
            $difference = round($newBase - $oldBase, 2);
            $adjustment = $this->add($locked, $difference < 0 ? 'decrease' : 'increase', abs($difference), $reason, $operationKey, $actor);
            $adjustment->update(['metadata' => ['kind' => 'provider_rate', 'old_rate' => $previous ? data_get($previous->metadata, 'new_rate') : ($base['unit_price'] ?? null), 'new_rate' => $rate, 'quantity' => $quantity, 'unit' => $base['unit'] ?? '', 'old_base' => $oldBase, 'new_base' => $newBase]]);
            return $adjustment;
        }, 3);
    }

    public function add(ServiceObligation $obligation, string $type, float $amount, string $reason, string $operationKey, User $actor, ?array $metadata = null): ServiceObligationAdjustment
    {
        return DB::transaction(function () use ($obligation, $type, $amount, $reason, $operationKey, $actor, $metadata) {
            if (! in_array($type, ['increase', 'decrease'], true)) {
                throw ValidationException::withMessages(['effect' => 'Selecione acréscimo ou desconto.']);
            }
            $amount = round(abs($amount) * ($type === 'decrease' ? -1 : 1), 2);
            $obligation = ServiceObligation::query()->where('tenant_id', $obligation->tenant_id)->whereKey($obligation->id)->lockForUpdate()->firstOrFail();
            if ($existing = ServiceObligationAdjustment::query()->where('tenant_id', $obligation->tenant_id)->where('operation_key', $operationKey)->first()) {
                if ($existing->service_obligation_id !== $obligation->id || abs((float) $existing->amount - $amount) > .0001
                    || $existing->reason !== $reason || $existing->metadata !== $metadata) {
                    throw ValidationException::withMessages(['operation_key' => 'A chave de operação já foi usada com outro conteúdo.']);
                }

                return $existing;
            }
            if ($obligation->status === 'cancelled' || $obligation->execution?->order?->operational_status === 'cancelled') {
                throw ValidationException::withMessages(['status' => 'Uma obrigação anulada não aceita ajustes.']);
            }
            if ($obligation->activePaymentPlan()
                || \App\Models\ServicePayoutRequestItem::query()->where('service_obligation_id', $obligation->id)
                    ->whereHas('request', fn ($query) => $query->where('tenant_id', $obligation->tenant_id)->whereIn('status', ['pending', 'approved']))->exists()) {
                throw ValidationException::withMessages(['amount' => 'Resolva a negociação ou a solicitação de pagamento em aberto antes de alterar este valor.']);
            }
            if (! is_finite($amount) || $amount == 0 || $obligation->total_amount + $amount > 999999999999.99) {
                throw ValidationException::withMessages(['amount' => 'Informe um ajuste diferente de zero e dentro do limite permitido.']);
            }
            if (mb_strlen(trim($reason)) < 5) {
                throw ValidationException::withMessages(['reason' => 'Informe o motivo do ajuste.']);
            }
            if ($obligation->total_amount + $amount < $obligation->paid_amount - .0001) {
                throw ValidationException::withMessages(['amount' => 'O ajuste reduziria a obrigação abaixo do valor já pago.']);
            }
            $adjustment = new ServiceObligationAdjustment(['service_obligation_id' => $obligation->id, 'operation_key' => $operationKey, 'type' => $type, 'amount' => $amount, 'reason' => $reason, 'created_by' => $actor->id, 'metadata' => $metadata]);
            $adjustment->tenant_id = $obligation->tenant_id;
            $adjustment->save();
            $newAdjustment = round((float) $obligation->adjustment_amount + $amount, 2);
            $paid = $obligation->paid_amount;
            $obligation->update(['adjustment_amount' => $newAdjustment, 'status' => $paid >= round((float) $obligation->principal_amount + $newAdjustment, 2) ? 'paid' : ($paid > 0 ? 'partially_paid' : 'open')]);

            return $adjustment;
        }, 3);
    }
}
