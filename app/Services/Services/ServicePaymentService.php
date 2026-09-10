<?php

namespace App\Services\Services;

use App\Enums\CashMovementType;
use App\Models\BankAccount;
use App\Models\CashMovement;
use App\Models\ServiceObligation;
use App\Models\ServicePaymentAllocation;
use App\Models\ServicePaymentEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ServicePaymentService
{
    public function record(ServiceObligation $obligation, float $amount, string $method, string $date, ?int $bankAccountId, string $operationKey, User $actor, array $metadata = []): ServicePaymentEvent
    {
        return DB::transaction(function () use ($obligation, $amount, $method, $date, $bankAccountId, $operationKey, $actor, $metadata): ServicePaymentEvent {
            $existing = ServicePaymentEvent::query()->where('tenant_id', $obligation->tenant_id)->where('operation_key', $operationKey)->first();
            if ($existing) {
                $existing->load('allocations');
                $allocation = $existing->allocations->first();
                if (! $allocation || $allocation->service_obligation_id !== $obligation->id || abs((float) $existing->amount - round($amount, 2)) > .0001) {
                    throw ValidationException::withMessages(['operation_key' => 'A chave de operação já foi usada com outro pagamento.']);
                }

return $existing;
            }
            $obligation = ServiceObligation::query()->whereKey($obligation->id)->where('tenant_id', $obligation->tenant_id)->lockForUpdate()->firstOrFail();
            $amount = round($amount, 2);
            if ($amount <= 0) {
                throw ValidationException::withMessages(['amount' => 'O valor deve ser maior que zero.']);
            }
            if ($obligation->status === 'cancelled') {
                throw ValidationException::withMessages(['status' => 'Obrigação cancelada não aceita pagamento.']);
            }
            if ($amount > $obligation->balance + 0.0001) {
                throw ValidationException::withMessages(['amount' => 'O pagamento não pode superar o saldo da obrigação.']);
            }
            $account = null;
            if ($bankAccountId) {
                $account = BankAccount::query()->whereKey($bankAccountId)->where('tenant_id', $obligation->tenant_id)->where('status', true)->lockForUpdate()->first();
                if (! $account) {
                    throw ValidationException::withMessages(['bank_account_id' => 'Conta inválida para esta organização.']);
                }
            }
            $event = new ServicePaymentEvent(['operation_key' => $operationKey, 'event_type' => 'payment', 'status' => 'confirmed', 'amount' => $amount, 'payment_method' => $method, 'payment_date' => $date, 'bank_account_id' => $account?->id, 'metadata' => $metadata, 'registered_by' => $actor->id]);
            $event->tenant_id = $obligation->tenant_id;
            $event->save();
            $allocation = new ServicePaymentAllocation(['service_payment_event_id' => $event->id, 'service_obligation_id' => $obligation->id, 'amount' => $amount]);
            $allocation->tenant_id = $obligation->tenant_id;
            $allocation->save();
            if ($account) {
                $movement = new CashMovement(['type' => $obligation->direction === 'receivable' ? CashMovementType::INCOME : CashMovementType::EXPENSE, 'amount' => $amount, 'description' => ($obligation->direction === 'receivable' ? 'Recebimento ' : 'Pagamento ao prestador ').$obligation->number, 'movement_date' => $date, 'bank_account_id' => $account->id, 'payment_method' => $method, 'document_number' => $obligation->number, 'created_by' => $actor->id]);
                $movement->tenant_id = $obligation->tenant_id;
                $movement->reference_type = ServicePaymentEvent::class;
                $movement->reference_id = $event->id;
                $movement->save();
                $event->update(['cash_movement_id' => $movement->id]);
            }
            $this->refreshStatus($obligation);
            activity('service_payment')->performedOn($event)->causedBy($actor)->withProperties(['tenant_id' => $obligation->tenant_id, 'obligation_id' => $obligation->id, 'direction' => $obligation->direction])->log('Pagamento de obrigação de serviço registrado');

            return $event->fresh(['allocations', 'cashMovement']);
        }, 3);
    }

    public function reverse(ServicePaymentEvent $payment, string $reason, string $operationKey, User $actor): ServicePaymentEvent
    {
        return DB::transaction(function () use ($payment, $reason, $operationKey, $actor): ServicePaymentEvent {
            if ($existing = ServicePaymentEvent::query()->where('tenant_id', $payment->tenant_id)->where('operation_key', $operationKey)->first()) {
                if ($existing->reversal_of_id !== $payment->id) {
                    throw ValidationException::withMessages(['operation_key' => 'A chave de operação já foi usada em outro estorno.']);
                }

return $existing->load('allocations');
            }
            $payment = ServicePaymentEvent::query()->whereKey($payment->id)->where('tenant_id', $payment->tenant_id)->lockForUpdate()->firstOrFail();
            if ($payment->event_type !== 'payment' || $payment->status !== 'confirmed' || $payment->reversal_of_id) {
                throw ValidationException::withMessages(['payment' => 'Pagamento não pode ser estornado ou já foi estornado.']);
            }
            if (mb_strlen(trim($reason)) < 5) {
                throw ValidationException::withMessages(['reason' => 'Informe o motivo do estorno.']);
            }
            $allocation = $payment->allocations()->lockForUpdate()->firstOrFail();
            $obligation = ServiceObligation::query()->whereKey($allocation->service_obligation_id)->where('tenant_id', $payment->tenant_id)->lockForUpdate()->firstOrFail();
            $account = null;
            if ($payment->bank_account_id) {
                $account = BankAccount::query()->whereKey($payment->bank_account_id)->where('tenant_id', $payment->tenant_id)->lockForUpdate()->firstOrFail();
            }
            $reversal = new ServicePaymentEvent(['operation_key' => $operationKey, 'event_type' => 'reversal', 'status' => 'confirmed', 'amount' => $payment->amount, 'payment_method' => $payment->payment_method, 'payment_date' => now()->toDateString(), 'bank_account_id' => $account?->id, 'reversal_of_id' => $payment->id, 'reason' => $reason, 'registered_by' => $actor->id]);
            $reversal->tenant_id = $payment->tenant_id;
            $reversal->save();
            $reverseAllocation = new ServicePaymentAllocation(['service_payment_event_id' => $reversal->id, 'service_obligation_id' => $obligation->id, 'amount' => -(float) $allocation->amount]);
            $reverseAllocation->tenant_id = $payment->tenant_id;
            $reverseAllocation->save();
            if ($account) {
                $movement = new CashMovement(['type' => $obligation->direction === 'receivable' ? CashMovementType::EXPENSE : CashMovementType::INCOME, 'amount' => $payment->amount, 'description' => 'Estorno '.$obligation->number, 'movement_date' => now()->toDateString(), 'bank_account_id' => $account->id, 'payment_method' => $payment->payment_method, 'document_number' => 'EST-'.$obligation->number, 'notes' => $reason, 'created_by' => $actor->id]);
                $movement->tenant_id = $payment->tenant_id;
                $movement->reference_type = ServicePaymentEvent::class;
                $movement->reference_id = $reversal->id;
                $movement->save();
                $reversal->update(['cash_movement_id' => $movement->id]);
            }
            $payment->update(['status' => 'reversed']);
            $this->refreshStatus($obligation);

            return $reversal->fresh(['allocations', 'cashMovement']);
        }, 3);
    }

    private function refreshStatus(ServiceObligation $obligation): void
    {
        $obligation->refresh();
        $paid = $obligation->paid_amount;
        $status = $paid <= 0 ? 'open' : ($paid + 0.0001 >= $obligation->total_amount ? 'paid' : 'partially_paid');
        $obligation->update(['status' => $status]);
    }
}
