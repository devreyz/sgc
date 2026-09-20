<?php

namespace App\Services\Services;

use App\Enums\CashMovementType;
use App\Enums\PaymentMethod;
use App\Models\BankAccount;
use App\Models\CashMovement;
use App\Models\ServiceObligation;
use App\Models\ServicePaymentAllocation;
use App\Models\ServicePaymentEvent;
use App\Models\ServicePaymentPlanInstallment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ServicePaymentService
{
    public function record(ServiceObligation $obligation, float $amount, string $method, string $date, ?int $bankAccountId, string $operationKey, User $actor, array $metadata = []): ServicePaymentEvent
    {
        return DB::transaction(function () use ($obligation, $amount, $method, $date, $bankAccountId, $operationKey, $actor, $metadata): ServicePaymentEvent {
            if (! is_finite($amount) || ! in_array($method, array_column(PaymentMethod::cases(), 'value'), true)) {
                throw ValidationException::withMessages(['amount' => 'Valor ou forma de pagamento inválida.']);
            }
            if (! $bankAccountId) {
                throw ValidationException::withMessages(['bank_account_id' => 'Selecione uma conta bancária ou caixa para registrar a movimentação.']);
            }
            $obligation = ServiceObligation::query()->whereKey($obligation->id)->where('tenant_id', $obligation->tenant_id)->lockForUpdate()->firstOrFail();
            $existing = ServicePaymentEvent::query()->where('tenant_id', $obligation->tenant_id)->where('operation_key', $operationKey)->first();
            if ($existing) {
                $existing->load('allocations');
                $allocation = $existing->allocations->first();
                if (! $allocation || $allocation->service_obligation_id !== $obligation->id || abs((float) $existing->amount - round($amount, 2)) > .0001 || $existing->payment_method !== $method || (int) $existing->bank_account_id !== $bankAccountId || $existing->payment_date->toDateString() !== $date) {
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

    public function recordInstallment(ServicePaymentPlanInstallment $installment, string $method, string $date, int $bankAccountId, string $operationKey, User $actor): ServicePaymentEvent
    {
        return DB::transaction(function () use ($installment, $method, $date, $bankAccountId, $operationKey, $actor): ServicePaymentEvent {
            if (! in_array($method, array_column(PaymentMethod::cases(), 'value'), true)) {
                throw ValidationException::withMessages(['payment_method' => 'Forma de pagamento inválida.']);
            }
            $existing = ServicePaymentEvent::query()->where('tenant_id', $installment->tenant_id)->where('operation_key', $operationKey)->first();
            if ($existing) {
                if ((int) data_get($existing->metadata, 'payment_plan_installment_id') !== (int) $installment->id) {
                    throw ValidationException::withMessages(['operation_key' => 'A chave de operação já foi usada em outro pagamento.']);
                }

                return $existing->load('allocations');
            }

            $installment = ServicePaymentPlanInstallment::query()
                ->where('tenant_id', $installment->tenant_id)
                ->whereKey($installment->id)
                ->with('plan.obligations')
                ->lockForUpdate()
                ->firstOrFail();
            if ($installment->status === 'paid' || $installment->service_payment_event_id) {
                throw ValidationException::withMessages(['installment' => 'Esta parcela já foi recebida.']);
            }

            $account = BankAccount::query()->whereKey($bankAccountId)
                ->where('tenant_id', $installment->tenant_id)->where('status', true)
                ->lockForUpdate()->first();
            if (! $account) {
                throw ValidationException::withMessages(['bank_account_id' => 'Conta inválida para esta organização.']);
            }

            $obligationIds = $installment->plan->obligations->pluck('id');
            $obligations = ServiceObligation::query()->where('tenant_id', $installment->tenant_id)
                ->whereIn('id', $obligationIds)->orderBy('id')->lockForUpdate()->get();
            $amount = round((float) $installment->amount, 2);
            if (round((float) $obligations->sum(fn (ServiceObligation $obligation) => $obligation->balance), 2) + 0.0001 < $amount) {
                throw ValidationException::withMessages(['installment' => 'O saldo atual das obrigações é menor que o valor desta parcela. Revise pagamentos já lançados.']);
            }

            $event = new ServicePaymentEvent([
                'operation_key' => $operationKey,
                'event_type' => 'payment',
                'status' => 'confirmed',
                'amount' => $amount,
                'payment_method' => $method,
                'payment_date' => $date,
                'bank_account_id' => $account->id,
                'metadata' => [
                    'payment_plan_id' => $installment->service_payment_plan_id,
                    'payment_plan_installment_id' => $installment->id,
                    'installment_number' => $installment->number,
                    'installment_kind' => $installment->kind,
                ],
                'registered_by' => $actor->id,
            ]);
            $event->tenant_id = $installment->tenant_id;
            $event->save();

            $remaining = $amount;
            foreach ($obligations as $obligation) {
                $allocated = min($remaining, $obligation->balance);
                if ($allocated <= 0) {
                    continue;
                }
                $allocation = new ServicePaymentAllocation([
                    'service_payment_event_id' => $event->id,
                    'service_obligation_id' => $obligation->id,
                    'amount' => round($allocated, 2),
                ]);
                $allocation->tenant_id = $installment->tenant_id;
                $allocation->save();
                $remaining = round($remaining - $allocated, 2);
                $this->refreshStatus($obligation);
            }

            $movement = new CashMovement([
                'type' => CashMovementType::INCOME,
                'amount' => $amount,
                'description' => ($installment->kind === 'entry' ? 'Entrada' : 'Parcela '.$installment->number).' de negociação de serviços',
                'movement_date' => $date,
                'bank_account_id' => $account->id,
                'payment_method' => $method,
                'document_number' => 'NEG-'.$installment->service_payment_plan_id.'-'.$installment->number,
                'created_by' => $actor->id,
            ]);
            $movement->tenant_id = $installment->tenant_id;
            $movement->reference_type = ServicePaymentEvent::class;
            $movement->reference_id = $event->id;
            $movement->save();
            $event->update(['cash_movement_id' => $movement->id]);
            $installment->update(['status' => 'paid', 'service_payment_event_id' => $event->id, 'paid_at' => now()]);
            if (! $installment->plan->installments()->where('status', '!=', 'paid')->exists()) {
                $installment->plan->update(['status' => 'completed']);
            }

            activity('service_payment')->performedOn($event)->causedBy($actor)->withProperties([
                'tenant_id' => $installment->tenant_id,
                'payment_plan_installment_id' => $installment->id,
            ])->log('Parcela de negociação de serviços recebida');

            return $event->fresh(['allocations', 'cashMovement']);
        }, 3);
    }

    public function reverse(ServicePaymentEvent $payment, string $reason, string $operationKey, User $actor): ServicePaymentEvent
    {
        return DB::transaction(function () use ($payment, $reason, $operationKey, $actor): ServicePaymentEvent {
            $payment = ServicePaymentEvent::query()->whereKey($payment->id)->where('tenant_id', $payment->tenant_id)->lockForUpdate()->firstOrFail();
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
            $allocations = $payment->allocations()->lockForUpdate()->get();
            if ($allocations->isEmpty()) {
                throw ValidationException::withMessages(['payment' => 'Pagamento sem alocação não pode ser estornado.']);
            }
            $obligations = ServiceObligation::query()->whereIn('id', $allocations->pluck('service_obligation_id'))
                ->where('tenant_id', $payment->tenant_id)->lockForUpdate()->get()->keyBy('id');
            $account = null;
            if ($payment->bank_account_id) {
                $account = BankAccount::query()->whereKey($payment->bank_account_id)->where('tenant_id', $payment->tenant_id)->lockForUpdate()->firstOrFail();
            }
            $reversal = new ServicePaymentEvent(['operation_key' => $operationKey, 'event_type' => 'reversal', 'status' => 'confirmed', 'amount' => $payment->amount, 'payment_method' => $payment->payment_method, 'payment_date' => now()->toDateString(), 'bank_account_id' => $account?->id, 'reversal_of_id' => $payment->id, 'reason' => $reason, 'registered_by' => $actor->id]);
            $reversal->tenant_id = $payment->tenant_id;
            $reversal->save();
            foreach ($allocations as $allocation) {
                $obligation = $obligations->get($allocation->service_obligation_id);
                $reverseAllocation = new ServicePaymentAllocation(['service_payment_event_id' => $reversal->id, 'service_obligation_id' => $allocation->service_obligation_id, 'amount' => -(float) $allocation->amount]);
                $reverseAllocation->tenant_id = $payment->tenant_id;
                $reverseAllocation->save();
                if ($obligation) {
                    $this->refreshStatus($obligation);
                }
            }
            if ($account) {
                $firstObligation = $obligations->first();
                $movement = new CashMovement(['type' => $firstObligation?->direction === 'payable' ? CashMovementType::INCOME : CashMovementType::EXPENSE, 'amount' => $payment->amount, 'description' => 'Estorno '.($firstObligation?->number ?? 'negociação de serviços'), 'movement_date' => now()->toDateString(), 'bank_account_id' => $account->id, 'payment_method' => $payment->payment_method, 'document_number' => 'EST-'.($firstObligation?->number ?? $payment->id), 'notes' => $reason, 'created_by' => $actor->id]);
                $movement->tenant_id = $payment->tenant_id;
                $movement->reference_type = ServicePaymentEvent::class;
                $movement->reference_id = $reversal->id;
                $movement->save();
                $reversal->update(['cash_movement_id' => $movement->id]);
            }
            $payment->update(['status' => 'reversed']);
            $installment = Schema::hasTable('service_payment_plan_installments')
                ? ServicePaymentPlanInstallment::query()->where('tenant_id', $payment->tenant_id)->where('service_payment_event_id', $payment->id)->lockForUpdate()->first()
                : null;
            if ($installment) {
                $installment->update(['status' => 'scheduled', 'service_payment_event_id' => null, 'paid_at' => null]);
                $installment->plan()->update(['status' => 'active']);
            }

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
