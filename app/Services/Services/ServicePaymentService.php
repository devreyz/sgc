<?php

namespace App\Services\Services;

use App\Enums\CashMovementType;
use App\Enums\PaymentMethod;
use App\Models\BankAccount;
use App\Models\CashMovement;
use App\Models\ServiceObligation;
use App\Models\ServicePaymentAllocation;
use App\Models\ServicePaymentEvent;
use App\Models\ServicePaymentPlan;
use App\Models\ServicePaymentPlanInstallment;
use App\Models\User;
use Illuminate\Support\Collection;
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
                if (! $allocation || $existing->event_type !== 'payment' || data_get($existing->metadata, 'payment_plan_id')
                    || $allocation->service_obligation_id !== $obligation->id || abs((float) $existing->amount - round($amount, 2)) > .0001
                    || $existing->payment_method !== $method || (int) $existing->bank_account_id !== $bankAccountId || $existing->payment_date->toDateString() !== $date) {
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
            if ($obligation->direction === 'receivable' && $obligation->activePaymentPlan()) {
                throw ValidationException::withMessages(['amount' => 'Este saldo está em um acordo ativo. Registre o recebimento na parcela correspondente.']);
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
            $event = new ServicePaymentEvent(['operation_key' => $operationKey, 'event_type' => 'payment', 'status' => 'confirmed', 'amount' => $amount, 'payment_method' => $method, 'payment_date' => $date, 'bank_account_id' => $account?->id, 'metadata' => $metadata + [
                'remaining_obligation_cents' => $obligation->balanceCents() - ServiceMoney::cents($amount),
                'registered_by_name' => $actor->getAttributes()['name'] ?? null,
                'payer_name' => data_get($obligation->party_snapshot, 'name'),
            ], 'registered_by' => $actor->id]);
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
        return $this->recordInstallments($installment->plan, [$installment->id => null], $method, $date, $bankAccountId, $operationKey, $actor);
    }

    public function recordInstallments(ServicePaymentPlan $plan, array $amounts, string $method, string $date, int $bankAccountId, string $operationKey, User $actor, array $details = []): ServicePaymentEvent
    {
        foreach ($amounts as $id => $amount) {
            if ($amount === null) {
                continue;
            }
            if (! is_numeric($amount) || ! preg_match('/^\+?\d{0,12}(?:\.\d{0,2})?$/D', (string) $amount)) {
                throw ValidationException::withMessages(['amounts' => 'Use valores em reais com até dois centavos decimais.']);
            }
            $amounts[$id] = ServiceMoney::decimal(ServiceMoney::cents((string) $amount));
        }
        ksort($amounts);
        $signature = hash('sha256', json_encode([$amounts, $method, $date, $bankAccountId, $details], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($plan, $amounts, $method, $date, $bankAccountId, $operationKey, $actor, $details, $signature): ServicePaymentEvent {
            $plan = ServicePaymentPlan::query()->where('tenant_id', $plan->tenant_id)->lockForUpdate()->findOrFail($plan->id);
            $existing = ServicePaymentEvent::query()->where('tenant_id', $plan->tenant_id)->where('operation_key', $operationKey)->lockForUpdate()->first();
            if ($existing) {
                $legacy = ! isset($existing->metadata['request_signature']) && count($amounts) === 1
                    && array_key_exists((int) data_get($existing->metadata, 'payment_plan_installment_id'), $amounts)
                    && ($amounts[array_key_first($amounts)] === null || $amounts[array_key_first($amounts)] === $existing->amount)
                    && $existing->payment_method === $method
                    && $existing->payment_date->toDateString() === $date && (int) $existing->bank_account_id === $bankAccountId;
                if ((int) data_get($existing->metadata, 'payment_plan_id') !== (int) $plan->id
                    || (data_get($existing->metadata, 'request_signature') !== $signature && ! $legacy)) {
                    throw ValidationException::withMessages(['operation_key' => 'Esta operação já foi usada em outro pagamento.']);
                }

                return $existing->load('allocations');
            }
            if ($plan->status !== 'active') {
                throw ValidationException::withMessages(['installment' => 'Este acordo não aceita novos recebimentos.']);
            }
            if ($amounts === [] || ! in_array($method, array_column(PaymentMethod::cases(), 'value'), true)) {
                throw ValidationException::withMessages(['payment_method' => 'Informe as parcelas e uma forma de pagamento válida.']);
            }
            $installments = $plan->installments()->where('tenant_id', $plan->tenant_id)
                ->whereIn('id', array_keys($amounts))->lockForUpdate()->get();
            if ($installments->count() !== count($amounts)) {
                throw ValidationException::withMessages(['amounts' => 'Selecione apenas parcelas deste acordo.']);
            }
            $obligationIds = DB::table('service_payment_plan_obligations')->where('service_payment_plan_id', $plan->id)
                ->lockForUpdate()->pluck('service_obligation_id');
            $obligations = ServiceObligation::query()->where('tenant_id', $plan->tenant_id)->where('direction', 'receivable')
                ->whereIn('id', $obligationIds)->orderBy('id')->lockForUpdate()->get();
            $account = BankAccount::query()->whereKey($bankAccountId)->where('tenant_id', $plan->tenant_id)
                ->where('status', true)->lockForUpdate()->first();
            if (! $account) {
                throw ValidationException::withMessages(['bank_account_id' => 'Conta inválida para esta organização.']);
            }

            $events = $plan->receiptEvents()->orderBy('id')->get();
            $balances = $obligations->mapWithKeys(fn (ServiceObligation $item) => [$item->id => $item->balanceCents()])->all();
            $distributions = [];
            $allocated = [];
            foreach ($installments as $installment) {
                $balance = $installment->balanceCents($events);
                $cents = $amounts[$installment->id] === null ? $balance : ServiceMoney::cents($amounts[$installment->id]);
                if ($installment->status !== 'scheduled' || $cents <= 0 || $cents > $balance) {
                    throw ValidationException::withMessages(['amounts' => 'O valor deve ser positivo e não pode superar o saldo da parcela.']);
                }

                $remaining = $cents;
                $byObligation = [];
                foreach ($balances as $id => $available) {
                    $portion = min($remaining, $available);
                    if ($portion > 0) {
                        $byObligation[$id] = $portion;
                        $allocated[$id] = ($allocated[$id] ?? 0) + $portion;
                        $balances[$id] -= $portion;
                        $remaining -= $portion;
                    }
                    if ($remaining === 0) {
                        break;
                    }
                }
                if ($remaining !== 0) {
                    throw ValidationException::withMessages(['amounts' => 'O saldo atual das ordens é menor que o valor informado.']);
                }
                $distributions[$installment->id] = [
                    'number' => $installment->number, 'kind' => $installment->kind,
                    'cents' => $cents, 'obligations' => $byObligation,
                ];
            }

            $event = new ServicePaymentEvent([
                'operation_key' => $operationKey, 'event_type' => 'payment', 'status' => 'confirmed',
                'amount' => ServiceMoney::decimal(array_sum($allocated)), 'payment_method' => $method,
                'payment_date' => $date, 'bank_account_id' => $account->id, 'registered_by' => $actor->id,
                'metadata' => array_merge($details, [
                    'payment_plan_id' => (int) $plan->id,
                    'payment_plan_installment_id' => count($distributions) === 1 ? array_key_first($distributions) : null,
                    'installments' => $distributions, 'request_signature' => $signature,
                    'remaining_plan_cents' => array_sum($balances),
                    'registered_by_name' => $actor->getAttributes()['name'] ?? null,
                ]),
            ]);
            $event->tenant_id = $plan->tenant_id;
            $event->save();

            foreach ($obligations as $obligation) {
                if (! isset($allocated[$obligation->id])) {
                    continue;
                }
                $allocation = new ServicePaymentAllocation([
                    'service_payment_event_id' => $event->id, 'service_obligation_id' => $obligation->id,
                    'amount' => ServiceMoney::decimal($allocated[$obligation->id]),
                ]);
                $allocation->tenant_id = $plan->tenant_id;
                $allocation->save();
                $this->refreshStatus($obligation);
            }

            $movement = new CashMovement([
                'type' => CashMovementType::INCOME, 'amount' => $event->amount,
                'description' => 'Recebimento de acordo de serviços', 'movement_date' => $date,
                'bank_account_id' => $account->id, 'payment_method' => $method,
                'document_number' => 'NEG-'.$plan->id.'-'.$event->id, 'notes' => $details['notes'] ?? null, 'created_by' => $actor->id,
            ]);
            $movement->tenant_id = $plan->tenant_id;
            $movement->reference_type = ServicePaymentEvent::class;
            $movement->reference_id = $event->id;
            $movement->save();
            $event->update(['cash_movement_id' => $movement->id]);

            $events->push($event);
            foreach ($installments as $installment) {
                $this->refreshInstallment($installment, $events);
            }
            if (! $plan->installments()->where('status', '!=', 'paid')->exists()) {
                $plan->update(['status' => 'completed']);
            }

            activity('service_payment')->performedOn($event)->causedBy($actor)
                ->withProperties(['payment_plan_id' => $plan->id, 'installments' => $distributions])
                ->log('Recebimento de acordo de serviços registrado');

            return $event->fresh(['allocations', 'cashMovement']);
        }, 3);
    }

    private function refreshInstallment(ServicePaymentPlanInstallment $installment, Collection $events): void
    {
        $paid = $installment->balanceCents($events) === 0;
        $lastPayment = $events->where('status', 'confirmed')->last(fn (ServicePaymentEvent $event) => $installment->eventCents($event) > 0);
        $installment->update([
            'status' => $paid ? 'paid' : 'scheduled',
            'service_payment_event_id' => $paid ? $lastPayment?->id : null,
            'paid_at' => $paid ? $lastPayment?->payment_date : null,
        ]);
    }

    public function correctReceiptAccount(ServicePaymentEvent $payment, int $accountId, string $reason, string $operationKey, User $actor): ServicePaymentEvent
    {
        return DB::transaction(function () use ($payment, $accountId, $reason, $operationKey, $actor): ServicePaymentEvent {
            $payment = ServicePaymentEvent::query()->where('tenant_id', $payment->tenant_id)
                ->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $existing = ServicePaymentEvent::query()->where('tenant_id', $payment->tenant_id)->where('operation_key', $operationKey)->first();
            if ($existing) {
                if ((int) data_get($existing->metadata, 'account_correction_of') !== (int) $payment->id
                    || (int) $existing->bank_account_id !== $accountId
                    || data_get($existing->metadata, 'account_correction_reason') !== $reason) {
                    throw ValidationException::withMessages(['operation_key' => 'Esta operação já foi usada com outros dados.']);
                }

                return $existing;
            }

            if ($payment->event_type !== 'payment' || $payment->status !== 'confirmed' || $payment->reversal_of_id
                || !$payment->bank_account_id || !$payment->cash_movement_id || data_get($payment->metadata, 'payment_plan_id')) {
                throw ValidationException::withMessages(['payment' => 'Selecione um recebimento direto, confirmado e vinculado a uma conta.']);
            }
            if ((int) $payment->bank_account_id === $accountId) {
                throw ValidationException::withMessages(['bank_account_id' => 'Escolha uma conta diferente da atual.']);
            }
            if (Schema::hasTable('service_payment_plan_installments')
                && ServicePaymentPlanInstallment::query()->where('tenant_id', $payment->tenant_id)->where('service_payment_event_id', $payment->id)->exists()) {
                throw ValidationException::withMessages(['payment' => 'Este recebimento pertence a um acordo. Confira a parcela no histórico da negociação.']);
            }

            $allocations = $payment->allocations()->with('obligation')->get();
            $allocation = $allocations->count() === 1 ? $allocations->first() : null;
            $obligation = $allocation?->obligation;
            if (!$obligation || $obligation->direction !== 'receivable' || (int) $obligation->tenant_id !== (int) $payment->tenant_id
                || abs((float) $allocation->amount - (float) $payment->amount) > .0001) {
                throw ValidationException::withMessages(['payment' => 'A correção de conta está disponível para recebimentos de uma única cobrança.']);
            }
            if (!$payment->cashMovement()->where('tenant_id', $payment->tenant_id)
                ->where('bank_account_id', $payment->bank_account_id)->where('type', CashMovementType::INCOME->value)->exists()) {
                throw ValidationException::withMessages(['payment' => 'O movimento original da conta não foi encontrado.']);
            }

            $date = $payment->payment_date->toDateString();
            $reversal = $this->reverse($payment, 'Correção de conta: '.$reason, (string) \Illuminate\Support\Str::uuid(), $actor, $date);

            return $this->record($obligation, (float) $payment->amount, $payment->payment_method, $date, $accountId, $operationKey, $actor, array_replace((array) $payment->metadata, [
                'account_correction_of' => $payment->id,
                'account_correction_reversal' => $reversal->id,
                'account_correction_reason' => $reason,
            ]));
        }, 3);
    }

    public function reverse(ServicePaymentEvent $payment, string $reason, string $operationKey, User $actor, ?string $effectiveDate = null): ServicePaymentEvent
    {
        return DB::transaction(function () use ($payment, $reason, $operationKey, $actor, $effectiveDate): ServicePaymentEvent {
            $planId = data_get($payment->metadata, 'payment_plan_id');
            $legacy = ! $planId && Schema::hasTable('service_payment_plan_installments')
                ? ServicePaymentPlanInstallment::query()->where('tenant_id', $payment->tenant_id)
                    ->where('service_payment_event_id', $payment->id)->lockForUpdate()->first()
                : null;
            $planId ??= $legacy?->service_payment_plan_id;
            $plan = $planId ? ServicePaymentPlan::query()->where('tenant_id', $payment->tenant_id)->lockForUpdate()->findOrFail($planId) : null;
            $payment = ServicePaymentEvent::query()->whereKey($payment->id)->where('tenant_id', $payment->tenant_id)->lockForUpdate()->firstOrFail();
            if ($existing = ServicePaymentEvent::query()->where('tenant_id', $payment->tenant_id)->where('operation_key', $operationKey)->lockForUpdate()->first()) {
                if ($existing->reversal_of_id !== $payment->id) {
                    throw ValidationException::withMessages(['operation_key' => 'A chave de operação já foi usada em outro estorno.']);
                }

                return $existing->load('allocations');
            }
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
                ->where('tenant_id', $payment->tenant_id)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            if ($obligations->contains(fn (ServiceObligation $obligation) => $obligation->activePaymentPlan($plan?->id))) {
                throw ValidationException::withMessages(['payment' => 'O estorno afeta outro acordo ativo. Revise o acordo vigente antes de estornar.']);
            }
            $account = null;
            if ($payment->bank_account_id) {
                $account = BankAccount::query()->whereKey($payment->bank_account_id)->where('tenant_id', $payment->tenant_id)->lockForUpdate()->firstOrFail();
            }
            $date = $effectiveDate ?? now()->toDateString();
            $reversal = new ServicePaymentEvent(['operation_key' => $operationKey, 'event_type' => 'reversal', 'status' => 'confirmed', 'amount' => $payment->amount, 'payment_method' => $payment->payment_method, 'payment_date' => $date, 'bank_account_id' => $account?->id, 'reversal_of_id' => $payment->id, 'reason' => $reason, 'registered_by' => $actor->id]);
            $reversal->tenant_id = $payment->tenant_id;
            $reversal->save();
            foreach ($allocations as $allocation) {
                $obligation = $obligations->get($allocation->service_obligation_id);
                $reverseAllocation = new ServicePaymentAllocation(['service_payment_event_id' => $reversal->id, 'service_obligation_id' => $allocation->service_obligation_id, 'amount' => ServiceMoney::decimal(-ServiceMoney::cents($allocation->amount))]);
                $reverseAllocation->tenant_id = $payment->tenant_id;
                $reverseAllocation->save();
                if ($obligation) {
                    $this->refreshStatus($obligation);
                }
            }
            if ($account) {
                $firstObligation = $obligations->first();
                $movement = new CashMovement(['type' => $firstObligation?->direction === 'payable' ? CashMovementType::INCOME : CashMovementType::EXPENSE, 'amount' => $payment->amount, 'description' => 'Estorno '.($firstObligation?->number ?? 'negociação de serviços'), 'movement_date' => $date, 'bank_account_id' => $account->id, 'payment_method' => $payment->payment_method, 'document_number' => 'EST-'.($firstObligation?->number ?? $payment->id), 'notes' => $reason, 'created_by' => $actor->id]);
                $movement->tenant_id = $payment->tenant_id;
                $movement->reference_type = ServicePaymentEvent::class;
                $movement->reference_id = $reversal->id;
                $movement->save();
                $reversal->update(['cash_movement_id' => $movement->id]);
            }
            $payment->update(['status' => 'reversed']);
            if ($plan && in_array($plan->status, ['active', 'completed'], true)) {
                $ids = array_keys(data_get($payment->metadata, 'installments', [])) ?: [
                    (int) (data_get($payment->metadata, 'payment_plan_installment_id') ?? $legacy?->id),
                ];
                $events = $plan->receiptEvents()->orderBy('id')->get();
                foreach ($plan->installments()->whereIn('id', $ids)->lockForUpdate()->get() as $installment) {
                    $this->refreshInstallment($installment, $events);
                }
                $plan->update(['status' => 'active']);
            }
            activity('service_payment')->performedOn($reversal)->causedBy($actor)
                ->withProperties(['reversal_of_id' => $payment->id, 'reason' => $reason])->log('Recebimento de serviço estornado');

            return $reversal->fresh(['allocations', 'cashMovement']);
        }, 3);
    }

    private function refreshStatus(ServiceObligation $obligation): void
    {
        $obligation->refresh();
        $paid = $obligation->paidCents();
        $status = $paid <= 0 ? 'open' : ($paid >= $obligation->totalCents() ? 'paid' : 'partially_paid');
        $obligation->update(['status' => $status]);
    }
}
