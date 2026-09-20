<?php

namespace App\Services;

use App\Models\FinancialCheckInstrument;
use App\Models\FinancialDocumentIdentity;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FinancialCheckService
{
    public function __construct(private readonly FinancialDocumentPaymentService $payments) {}

    public function issue(FinancialDocumentIdentity $identity, array $data, User $actor): FinancialCheckInstrument
    {
        return DB::transaction(function () use ($identity, $data, $actor): FinancialCheckInstrument {
            $identity = FinancialDocumentIdentity::withoutGlobalScopes()
                ->where('tenant_id', $identity->tenant_id)->lockForUpdate()->findOrFail($identity->id);
            $identity->load('documentable');
            $operationKey = strtolower((string) $data['operation_key']);
            if (! Str::isUuid($operationKey)) {
                throw ValidationException::withMessages(['operation_key' => 'A chave técnica da emissão é inválida.']);
            }
            $existing = FinancialCheckInstrument::withoutGlobalScopes()
                ->where('tenant_id', $identity->tenant_id)->where('operation_key', $operationKey)->first();
            if ($existing) {
                return $existing;
            }
            if ($identity->checks()->where('status', 'issued')->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['check' => 'Já existe um cheque emitido aguardando entrega para este comprovante.']);
            }
            $amount = round((float) $data['amount'], 2);
            $balance = $this->payments->balance($identity);
            if ($amount <= 0 || $amount > $balance + .0001) {
                throw ValidationException::withMessages(['amount' => 'O cheque deve ter valor positivo e não pode superar o saldo do comprovante.']);
            }

            $check = FinancialCheckInstrument::create([
                'tenant_id' => $identity->tenant_id,
                'financial_document_identity_id' => $identity->id,
                'operation_key' => $operationKey,
                'amount' => $amount,
                'check_number' => $data['check_number'],
                'bank_name' => $data['bank_name'],
                'account_reference' => $data['account_reference'] ?? null,
                'issue_date' => $data['issue_date'],
                'expected_delivery_date' => $data['expected_delivery_date'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => 'issued',
                'issued_at' => now(),
                'issued_by' => $actor->id,
            ]);

            activity('financial_check')->performedOn($check)->causedBy($actor)->withProperties([
                'tenant_id' => $identity->tenant_id,
                'identity_id' => $identity->id,
                'amount' => $amount,
                'status' => 'issued',
            ])->log('Cheque emitido e aguardando entrega');

            return $check;
        }, 3);
    }

    public function deliver(FinancialCheckInstrument $check, array $data, User $actor): FinancialCheckInstrument
    {
        return DB::transaction(function () use ($check, $data, $actor): FinancialCheckInstrument {
            $check = FinancialCheckInstrument::withoutGlobalScopes()
                ->where('tenant_id', $check->tenant_id)->lockForUpdate()->findOrFail($check->id);
            $deliveryKey = strtolower((string) $data['operation_key']);
            if (! Str::isUuid($deliveryKey)) {
                throw ValidationException::withMessages(['operation_key' => 'A chave técnica da entrega é inválida.']);
            }
            if ($check->status === 'delivered') {
                if ($check->delivery_operation_key === $deliveryKey) {
                    return $check;
                }
                throw ValidationException::withMessages(['check' => 'Este cheque já foi entregue.']);
            }
            if ($check->status !== 'issued') {
                throw ValidationException::withMessages(['check' => 'Este cheque não está disponível para entrega.']);
            }
            $identity = FinancialDocumentIdentity::withoutGlobalScopes()
                ->where('tenant_id', $check->tenant_id)->with('documentable')->lockForUpdate()
                ->findOrFail($check->financial_document_identity_id);

            $this->payments->pay($identity, [
                'amount' => $check->amount,
                'operation_key' => $deliveryKey,
                'payment_date' => $data['payment_date'],
                'payment_method' => 'cheque',
                'bank_account_id' => $data['bank_account_id'],
                'document_number' => $check->check_number,
                'notes' => $data['notes'] ?? $check->notes,
                'check_delivery' => true,
            ], $actor);

            $check->update([
                'status' => 'delivered',
                'delivery_operation_key' => $deliveryKey,
                'delivered_at' => now(),
                'delivered_by' => $actor->id,
            ]);
            activity('financial_check')->performedOn($check)->causedBy($actor)->withProperties([
                'tenant_id' => $check->tenant_id,
                'identity_id' => $identity->id,
                'amount' => (float) $check->amount,
                'status' => 'delivered',
            ])->log('Cheque entregue e liquidação registrada');

            return $check;
        }, 3);
    }

    public function cancel(FinancialCheckInstrument $check, string $reason, User $actor): FinancialCheckInstrument
    {
        return DB::transaction(function () use ($check, $reason, $actor): FinancialCheckInstrument {
            $check = FinancialCheckInstrument::withoutGlobalScopes()
                ->where('tenant_id', $check->tenant_id)->lockForUpdate()->findOrFail($check->id);
            if ($check->status !== 'issued') {
                throw ValidationException::withMessages(['check' => 'Somente um cheque emitido e ainda não entregue pode ser cancelado.']);
            }
            $check->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancelled_by' => $actor->id,
                'cancellation_reason' => $reason,
            ]);
            activity('financial_check')->performedOn($check)->causedBy($actor)->withProperties([
                'tenant_id' => $check->tenant_id,
                'identity_id' => $check->financial_document_identity_id,
                'amount' => (float) $check->amount,
                'status' => 'cancelled',
                'reason' => $reason,
            ])->log('Cheque cancelado antes da entrega');

            return $check;
        }, 3);
    }
}
