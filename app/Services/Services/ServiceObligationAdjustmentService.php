<?php

namespace App\Services\Services;

use App\Models\ServiceObligation;
use App\Models\ServiceObligationAdjustment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ServiceObligationAdjustmentService
{
    public function add(ServiceObligation $obligation, string $type, float $amount, string $reason, string $operationKey, User $actor): ServiceObligationAdjustment
    {
        return DB::transaction(function () use ($obligation, $type, $amount, $reason, $operationKey, $actor) {
            if (! in_array($type, ['increase', 'decrease'], true)) {
                throw ValidationException::withMessages(['effect' => 'Selecione acréscimo ou desconto.']);
            }
            $amount = abs($amount) * ($type === 'decrease' ? -1 : 1);
            if ($existing = ServiceObligationAdjustment::query()->where('tenant_id', $obligation->tenant_id)->where('operation_key', $operationKey)->first()) {
                if ($existing->service_obligation_id !== $obligation->id || abs((float) $existing->amount - $amount) > .0001) {
                    throw ValidationException::withMessages(['operation_key' => 'A chave de operação já foi usada com outro conteúdo.']);
                }

                return $existing;
            }$obligation = ServiceObligation::query()->where('tenant_id', $obligation->tenant_id)->whereKey($obligation->id)->lockForUpdate()->firstOrFail();
            $amount = round($amount, 2);
            if ($amount == 0) {
                throw ValidationException::withMessages(['amount' => 'O ajuste não pode ser zero.']);
            }if (mb_strlen(trim($reason)) < 5) {
                throw ValidationException::withMessages(['reason' => 'Informe o motivo do ajuste.']);
            }if ($obligation->total_amount + $amount < $obligation->paid_amount - .0001) {
                throw ValidationException::withMessages(['amount' => 'O ajuste reduziria a obrigação abaixo do valor já pago.']);
            }$adjustment = new ServiceObligationAdjustment(['service_obligation_id' => $obligation->id, 'operation_key' => $operationKey, 'type' => $type, 'amount' => $amount, 'reason' => $reason, 'created_by' => $actor->id]);
            $adjustment->tenant_id = $obligation->tenant_id;
            $adjustment->save();
            $obligation->update(['adjustment_amount' => round((float) $obligation->adjustment_amount + $amount, 2)]);

            return $adjustment;
        }, 3);
    }
}
