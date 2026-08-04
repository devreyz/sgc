<?php

namespace App\Services;

use App\Enums\CashMovementType;
use App\Enums\FinancialReceiptStatus;
use App\Models\BankAccount;
use App\Models\CashMovement;
use App\Models\ChartAccount;
use App\Models\FinancialReceipt;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class FinancialReceiptService
{
    public function createDraft(int $tenantId, array $data, User $user): FinancialReceipt
    {
        Gate::forUser($user)->authorize('create', FinancialReceipt::class);

        return DB::transaction(function () use ($tenantId, $data, $user): FinancialReceipt {
            $this->validateTenantReferences($tenantId, $data);

            $receipt = new FinancialReceipt($this->receiptAttributes($data));
            $receipt->tenant_id = $tenantId;
            $receipt->status = FinancialReceiptStatus::DRAFT;
            $receipt->created_by = $user->id;
            $receipt->save();

            $this->replaceItems($receipt, $data['items'] ?? []);
            $receipt->recalculateTotal();

            activity('financial_receipt')->performedOn($receipt)->causedBy($user)
                ->withProperties(['tenant_id' => $tenantId, 'action' => 'draft_created', 'items_count' => count($data['items'] ?? [])])
                ->log('Rascunho de recibo de recebimento criado');

            return $receipt->fresh(['items', 'bankAccount', 'chartAccount']);
        }, 3);
    }

    public function updateDraft(FinancialReceipt $receipt, array $data, User $user): FinancialReceipt
    {
        Gate::forUser($user)->authorize('update', $receipt);

        return DB::transaction(function () use ($receipt, $data, $user): FinancialReceipt {
            $receipt = FinancialReceipt::query()->lockForUpdate()->findOrFail($receipt->id);
            if (! $receipt->isDraft()) {
                throw ValidationException::withMessages(['status' => 'Somente recibos em rascunho podem ser alterados.']);
            }

            $this->validateTenantReferences((int) $receipt->tenant_id, $data);
            $receipt->fill($this->receiptAttributes($data))->save();
            $receipt->items()->delete();
            $this->replaceItems($receipt, $data['items'] ?? []);
            $receipt->recalculateTotal();

            activity('financial_receipt')->performedOn($receipt)->causedBy($user)
                ->withProperties(['tenant_id' => $receipt->tenant_id, 'action' => 'draft_updated', 'items_count' => count($data['items'] ?? [])])
                ->log('Rascunho de recibo de recebimento atualizado');

            return $receipt->fresh(['items', 'bankAccount', 'chartAccount']);
        }, 3);
    }

    public function issue(FinancialReceipt $receipt, User $user): FinancialReceipt
    {
        Gate::forUser($user)->authorize('issue', $receipt);

        return DB::transaction(function () use ($receipt, $user): FinancialReceipt {
            $receipt = FinancialReceipt::query()->lockForUpdate()->findOrFail($receipt->id);

            if (! $receipt->isDraft()) {
                throw ValidationException::withMessages(['status' => 'Somente recibos em rascunho podem ser emitidos.']);
            }

            $items = $receipt->items()->lockForUpdate()->get();
            $total = $items->isNotEmpty()
                ? round((float) $items->sum('total_amount'), 2)
                : round((float) $receipt->manual_amount, 2);
            if ($total <= 0) {
                throw ValidationException::withMessages(['total_amount' => 'O total do recebimento deve ser maior que zero.']);
            }

            $account = BankAccount::query()
                ->whereKey($receipt->bank_account_id)
                ->where('tenant_id', $receipt->tenant_id)
                ->where('status', true)
                ->lockForUpdate()
                ->first();

            if (! $account) {
                throw ValidationException::withMessages(['bank_account_id' => 'A conta de recebimento não está ativa ou não pertence à organização.']);
            }

            $year = (int) $receipt->received_on->format('Y');
            DB::table('financial_receipt_counters')->insertOrIgnore([
                'tenant_id' => $receipt->tenant_id,
                'year' => $year,
                'last_number' => 0,
            ]);
            $counter = DB::table('financial_receipt_counters')
                ->where('tenant_id', $receipt->tenant_id)
                ->where('year', $year)
                ->lockForUpdate()
                ->first();
            $number = ((int) $counter->last_number) + 1;
            DB::table('financial_receipt_counters')
                ->where('tenant_id', $receipt->tenant_id)
                ->where('year', $year)
                ->update(['last_number' => $number]);

            $newBalance = round((float) $account->current_balance + $total, 2);
            $movement = new CashMovement([
                'type' => CashMovementType::INCOME,
                'amount' => $total,
                'balance_after' => $newBalance,
                'description' => 'Recebimento '.$receipt->payer_name,
                'movement_date' => $receipt->received_on,
                'bank_account_id' => $account->id,
                'chart_account_id' => $receipt->chart_account_id,
                'payment_method' => $receipt->payment_method,
                'document_number' => sprintf('REC-%d/%06d', $year, $number),
                'notes' => $receipt->purpose,
                'created_by' => $user->id,
            ]);
            $movement->tenant_id = $receipt->tenant_id;
            $movement->reference_type = FinancialReceipt::class;
            $movement->reference_id = $receipt->id;
            $movement->save();

            $receipt->forceFill([
                'receipt_year' => $year,
                'receipt_number' => $number,
                'status' => FinancialReceiptStatus::ISSUED,
                'total_amount' => $total,
                'issued_by' => $user->id,
                'issued_at' => now(),
                'cash_movement_id' => $movement->id,
            ])->save();

            activity('financial_receipt')->performedOn($receipt)->causedBy($user)
                ->withProperties(['tenant_id' => $receipt->tenant_id, 'action' => 'issued', 'cash_movement_id' => $movement->id])
                ->log('Recibo de recebimento emitido');

            return $receipt->fresh(['items', 'bankAccount', 'issuer']);
        }, 3);
    }

    public function cancel(FinancialReceipt $receipt, User $user, string $reason): FinancialReceipt
    {
        Gate::forUser($user)->authorize('cancel', $receipt);

        $reason = trim($reason);
        if (mb_strlen($reason) < 10) {
            throw ValidationException::withMessages(['cancellation_reason' => 'Informe um motivo de cancelamento com pelo menos 10 caracteres.']);
        }

        return DB::transaction(function () use ($receipt, $user, $reason): FinancialReceipt {
            $receipt = FinancialReceipt::query()->lockForUpdate()->findOrFail($receipt->id);
            if (! $receipt->isIssued() || $receipt->reversal_movement_id) {
                throw ValidationException::withMessages(['status' => 'Este recibo não pode ser cancelado ou já foi estornado.']);
            }

            $account = BankAccount::query()
                ->whereKey($receipt->bank_account_id)
                ->where('tenant_id', $receipt->tenant_id)
                ->lockForUpdate()->firstOrFail();
            $newBalance = round((float) $account->current_balance - (float) $receipt->total_amount, 2);

            $movement = new CashMovement([
                'type' => CashMovementType::EXPENSE,
                'amount' => $receipt->total_amount,
                'balance_after' => $newBalance,
                'description' => 'Estorno do '.$receipt->formatted_number,
                'movement_date' => now()->toDateString(),
                'bank_account_id' => $account->id,
                'chart_account_id' => $receipt->chart_account_id,
                'payment_method' => $receipt->payment_method,
                'document_number' => 'EST-'.$receipt->formatted_number,
                'notes' => $reason,
                'created_by' => $user->id,
            ]);
            $movement->tenant_id = $receipt->tenant_id;
            $movement->reference_type = FinancialReceipt::class;
            $movement->reference_id = $receipt->id;
            $movement->save();

            $receipt->forceFill([
                'status' => FinancialReceiptStatus::CANCELLED,
                'cancelled_by' => $user->id,
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
                'reversal_movement_id' => $movement->id,
            ])->save();

            activity('financial_receipt')->performedOn($receipt)->causedBy($user)
                ->withProperties(['tenant_id' => $receipt->tenant_id, 'action' => 'cancelled', 'reversal_movement_id' => $movement->id])
                ->log('Recibo de recebimento cancelado e estornado');

            return $receipt->fresh();
        }, 3);
    }

    private function receiptAttributes(array $data): array
    {
        return collect($data)->only([
            'payer_type', 'payer_name', 'payer_document', 'payer_contact', 'received_on',
            'bank_account_id', 'chart_account_id', 'payment_method', 'payment_reference',
            'manual_amount', 'purpose', 'notes',
        ])->all();
    }

    private function validateTenantReferences(int $tenantId, array $data): void
    {
        $accountIsValid = BankAccount::query()
            ->whereKey($data['bank_account_id'])
            ->where('tenant_id', $tenantId)
            ->where('status', true)
            ->exists();

        if (! $accountIsValid) {
            throw ValidationException::withMessages(['bank_account_id' => 'A conta selecionada não pertence a esta organização ou está inativa.']);
        }

        if (! empty($data['chart_account_id'])) {
            $chartAccountIsValid = ChartAccount::query()
                ->whereKey($data['chart_account_id'])
                ->where('tenant_id', $tenantId)
                ->where('status', true)
                ->where('allows_entries', true)
                ->exists();

            if (! $chartAccountIsValid) {
                throw ValidationException::withMessages(['chart_account_id' => 'A classificação selecionada não aceita lançamentos nesta organização.']);
            }
        }
    }

    private function replaceItems(FinancialReceipt $receipt, array $items): void
    {
        foreach (array_values($items) as $index => $item) {
            $receipt->items()->create([
                'position' => $index + 1,
                'description' => trim($item['description']),
                'quantity' => $item['quantity'],
                'unit' => trim($item['unit']),
                'unit_price' => $item['unit_price'],
                'reference' => filled($item['reference'] ?? null) ? trim($item['reference']) : null,
            ]);
        }
    }
}
