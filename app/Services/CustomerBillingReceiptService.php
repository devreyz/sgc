<?php

namespace App\Services;

use App\Enums\BillingStatus;
use App\Enums\CashMovementType;
use App\Enums\CustomerReceiptStatus;
use App\Models\BankAccount;
use App\Models\CashMovement;
use App\Models\CustomerBillingReceipt;
use App\Models\CustomerProjectFee;
use App\Models\ProductionDelivery;
use App\Models\SalesProject;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Serviço central do fluxo de contas a receber (cliente).
 *
 * Fluxo: Distribuições → Comprovante (snapshot) → Recebimento → CashMovement INCOME
 *
 * Regra de unicidade:
 *   Uma distribuição pode estar em UM ÚNICO comprovante de cliente em status PAID.
 *   Distribuições em rascunho/pendente podem ser realocadas.
 *
 * Responsabilidades:
 *  - computeSnapshot:      calcula o resumo financeiro das distribuições
 *  - freezeReceipt:        congela o snapshot e vincula as distribuições
 *  - payReceipt:           registra o recebimento, cria CashMovement INCOME
 *  - validateDistributions: verifica se as distribuições podem ser vinculadas
 */
class CustomerBillingReceiptService
{
    public function __construct(
        private readonly ProjectFinancialCalculator $calculator
    ) {}

    // ─────────────────────────────────────────────────────────────────────────
    //  Snapshot financeiro
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Calcula o resumo financeiro de um conjunto de distribuições.
     *
     * Regra de taxas do cliente:
     *   - Somente as customer_project_fees do projeto são consideradas.
     *   - Se não houver nenhuma taxa configurada, nenhuma dedução é aplicada
     *     (bruto = líquido). Nunca usa as taxas do associado como fallback.
     *
     * @return array{
     *   total_gross: string,
     *   total_fees: string,
     *   total_net: string,
     *   fee_snapshot: array,
     * }
     */
    public function computeSnapshot(Collection $distributions, SalesProject $project): array
    {
        // Carrega taxas específicas do cliente; se vazia, zero deduções aplicadas
        $customerFees = CustomerProjectFee::where('sales_project_id', $project->id)
            ->where('active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $totalGross = '0';
        $totalFees  = '0';
        $totalNet   = '0';
        $feeDetails = null;

        foreach ($distributions as $dist) {
            $gross = $dist->gross_value ?? null;
            if ($gross === null || bccomp((string) $gross, '0', 8) <= 0) {
                $gross = bcmul((string) ($dist->quantity ?? 0), (string) ($dist->unit_price ?? 0), 8);
            }
            $gross = (string) $gross;

            if ($customerFees->isEmpty()) {
                // Sem taxas configuradas → bruto = líquido, sem deduções
                $result = [
                    'net'       => $gross,
                    'total_fee' => '0',
                    'fees'      => [],
                ];
            } else {
                $result = $this->calculator->calculateWithFees($project, $gross, $customerFees);
            }

            $totalGross = bcadd($totalGross, $gross, 8);
            $totalFees  = bcadd($totalFees, $result['total_fee'], 8);
            $totalNet   = bcadd($totalNet, $result['net'], 8);

            if ($feeDetails === null) {
                $feeDetails = $result['fees'];
            }
        }

        $feeSnapshot = [
            'fees'               => $feeDetails ?? [],
            'total_discounts'    => $totalFees,
            'total_accruals'     => '0',
            'total_fee'          => $totalFees,
            'distribution_count' => $distributions->count(),
            'fee_source'         => $customerFees->isNotEmpty() ? 'customer_project_fees' : 'no_fees',
        ];

        return [
            'total_gross'  => $totalGross,
            'total_fees'   => $totalFees,
            'total_net'    => $totalNet,
            'fee_snapshot' => $feeSnapshot,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Congelar comprovante
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Congela o snapshot financeiro no comprovante e vincula as distribuições.
     *
     * ── Segurança (Fase 4) ──────────────────────────────────────────────────
     * Dentro da transação, aplica SELECT FOR UPDATE nas linhas das distribuições
     * antes de qualquer escrita. Isso garante exclusividade: se dois processos
     * simultâneos tentarem congelar as mesmas distribuições, o segundo será
     * bloqueado até o primeiro terminar, e então falhará ao detectar
     * billing_receipt_id já preenchido.
     *
     * Após o UPDATE, verifica se o número de linhas afetadas bate com o esperado.
     * Se não bater, lança exceção e a transação é revertida.
     *
     * @throws \RuntimeException Se alguma distribuição já estiver em comprovante PAGO
     *                           ou se houver race condition detectada.
     */
    public function freezeReceipt(
        CustomerBillingReceipt $receipt,
        Collection $distributions,
        SalesProject $project
    ): void {
        $ids = $distributions->pluck('id')->filter()->values()->all();

        if (empty($ids)) {
            throw new \RuntimeException('Nenhuma distribuição selecionada para o comprovante.');
        }

        $snapshot = $this->computeSnapshot($distributions, $project);

        DB::transaction(function () use ($receipt, $snapshot, $ids) {
            // ── 1. Pessimistic lock: bloqueia as linhas para escrita exclusiva ──
            $locked = ProductionDelivery::whereIn('id', $ids)
                ->lockForUpdate()
                ->get(['id', 'billing_receipt_id']);

            // ── 2. Validação DENTRO da transação (após lock, antes do UPDATE) ──
            $alreadyClaimed = $locked->filter(function ($d) use ($receipt) {
                // Já vinculada a OUTRO comprovante (não o atual)
                return ! is_null($d->billing_receipt_id)
                    && $d->billing_receipt_id !== $receipt->id;
            });

            // Verifica se alguma das já vinculadas tem recibo PAGO
            $blockedByPaid = $alreadyClaimed->filter(function ($d) {
                return \App\Models\CustomerBillingReceipt::where('id', $d->billing_receipt_id)
                    ->where('status', CustomerReceiptStatus::PAID->value)
                    ->exists();
            });

            if ($blockedByPaid->isNotEmpty()) {
                throw new \RuntimeException(
                    'As distribuições a seguir já estão em um comprovante de cliente pago e não podem ser realocadas: '
                    . $blockedByPaid->pluck('id')->implode(', ')
                );
            }

            // ── 3. Snapshot no comprovante ─────────────────────────────────────
            $receipt->updateQuietly([
                'total_gross'  => $snapshot['total_gross'],
                'total_fees'   => $snapshot['total_fees'],
                'total_net'    => $snapshot['total_net'],
                'fee_snapshot' => $snapshot['fee_snapshot'],
                'delivery_ids' => $ids,
                'status'       => CustomerReceiptStatus::PENDING_PAYMENT->value,
            ]);

            // ── 4. Vincular distribuições (apenas as que ainda não têm vínculo ativo) ─
            $freeIds = $locked
                ->filter(fn ($d) => is_null($d->billing_receipt_id) || $d->billing_receipt_id === $receipt->id)
                ->pluck('id')
                ->all();

            if (! empty($freeIds)) {
                $affected = ProductionDelivery::whereIn('id', $freeIds)
                    ->update(['billing_receipt_id' => $receipt->id]);

                // ── 5. Verificação de integridade: detecta race condition residual ──
                if ($affected !== count($freeIds)) {
                    throw new \RuntimeException(
                        'Race condition detectada: apenas ' . $affected . ' de ' . count($freeIds)
                        . ' distribuições foram vinculadas. Tente novamente.'
                    );
                }
            }
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Receber comprovante (INCOME)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Registra o recebimento de um comprovante de cliente.
     *
     * O que acontece:
     *  1. Cria UM CashMovement de tipo INCOME (entrada no caixa)
     *  2. Atualiza o saldo da conta bancária (+ valor líquido)
     *  3. Marca o comprovante como PAID
     *  4. Marca as distribuições vinculadas com billing_status = PAID
     *
     * @param  array{
     *   payment_date: string|null,
     *   payment_method: string|null,
     *   bank_account_id: int|null,
     *   document_number: string|null,
     *   notes: string|null,
     * } $data
     *
     * @throws \RuntimeException Se o comprovante já foi pago ou sem valor líquido.
     */
    /**
     * Registra um recebimento (total ou parcial) para o comprovante.
     *
     * @param  array{
     *   amount: float|string,
     *   payment_date: string,
     *   payment_method: string|null,
     *   bank_account_id: int|null,
     *   document_number: string|null,
     *   notes: string|null,
     * } $data
     */
    public function addPayment(CustomerBillingReceipt $receipt, array $data): void
    {
        if ($receipt->status === CustomerReceiptStatus::PAID) {
            throw new \RuntimeException('Este comprovante já foi integralmente recebido.');
        }
        if (! in_array($receipt->status, [
            CustomerReceiptStatus::PENDING_PAYMENT,
            CustomerReceiptStatus::PARTIALLY_PAID,
        ])) {
            throw new \RuntimeException(
                'O comprovante precisa estar emitido (Aguardando Recebimento) para registrar um pagamento.'
            );
        }

        $netValue = (string) ($receipt->total_net ?? 0);
        if (bccomp($netValue, '0', 8) <= 0) {
            throw new \RuntimeException(
                'O comprovante não possui valor líquido congelado. Emita-o antes de registrar recebimento.'
            );
        }

        $amount       = bcadd((string) $data['amount'], '0', 8);
        $remaining    = bcsub($netValue, (string) ($receipt->amount_paid ?? 0), 8);
        if (bccomp($amount, '0', 8) <= 0) {
            throw new \RuntimeException('O valor do recebimento deve ser maior que zero.');
        }
        if (bccomp($amount, $remaining, 8) > 0) {
            throw new \RuntimeException(
                'O valor informado (R$ ' . number_format((float) $amount, 2, ',', '.') .
                ') excede o saldo restante (R$ ' . number_format((float) $remaining, 2, ',', '.') . ').'
            );
        }

        $recipientName = $receipt->recipient_name ?? ($receipt->customer?->name ?? $receipt->organization?->name ?? '—');
        $projectTitle  = optional($receipt->project)->title ?? 'Projeto';
        $paymentDate   = $data['payment_date'] ?? now()->toDateString();

        DB::transaction(function () use ($receipt, $data, $amount, $netValue, $recipientName, $projectTitle, $paymentDate) {
            // ── Registrar parcela de pagamento ─────────────────────────────
            \App\Models\CustomerReceiptPayment::create([
                'tenant_id'                    => $receipt->tenant_id,
                'customer_billing_receipt_id'  => $receipt->id,
                'amount'                       => round((float) $amount, 2),
                'payment_date'                 => $paymentDate,
                'payment_method'               => $data['payment_method'] ?? null,
                'bank_account_id'              => $data['bank_account_id'] ?? null,
                'document_number'              => $data['document_number'] ?? null,
                'notes'                        => $data['notes'] ?? null,
                'created_by'                   => Auth::id(),
            ]);

            // ── Atualizar amount_paid ──────────────────────────────────────
            $newPaid = bcadd((string) ($receipt->amount_paid ?? 0), $amount, 8);
            $isFull  = bccomp($newPaid, $netValue, 2) >= 0;

            $updateData = [
                'amount_paid'    => round((float) $newPaid, 2),
                'status'         => $isFull
                    ? CustomerReceiptStatus::PAID->value
                    : CustomerReceiptStatus::PARTIALLY_PAID->value,
            ];

            if ($isFull) {
                $updateData['paid_at']         = now();
                $updateData['paid_by']         = Auth::id();
                $updateData['payment_method']  = $data['payment_method'] ?? null;
                $updateData['bank_account_id'] = $data['bank_account_id'] ?? null;
                $updateData['document_number'] = $data['document_number'] ?? null;
                $updateData['payment_notes']   = $data['notes'] ?? null;
            }
            $receipt->update($updateData);

            // ── Marcar distribuições como pagas (somente se quitado) ───────
            if ($isFull) {
                $deliveryIds = $receipt->delivery_ids ?? [];
                if (! empty($deliveryIds)) {
                    ProductionDelivery::whereIn('id', $deliveryIds)
                        ->update(['billing_status' => BillingStatus::PAID->value]);
                }
            }

            // ── Movimento de caixa (entrada proporcional) ──────────────────
            if (! empty($data['bank_account_id'])) {
                $bankAccount = BankAccount::find($data['bank_account_id']);
                if ($bankAccount) {
                    $currentBankBal = (string) ($bankAccount->current_balance ?? 0);
                    $newBankBal     = bcadd($currentBankBal, $amount, 8);

                    CashMovement::create([
                        'tenant_id'       => $receipt->tenant_id,
                        'type'            => CashMovementType::INCOME,
                        'amount'          => round((float) $amount, 2),
                        'balance_after'   => round((float) $newBankBal, 2),
                        'description'     => "Recebimento cliente — {$recipientName} — {$projectTitle} — {$receipt->formatted_number}",
                        'movement_date'   => $paymentDate,
                        'bank_account_id' => $data['bank_account_id'],
                        'reference_type'  => CustomerBillingReceipt::class,
                        'reference_id'    => $receipt->id,
                        'payment_method'  => $data['payment_method'] ?? null,
                        'document_number' => $data['document_number'] ?? null,
                        'notes'           => $data['notes'] ?? null,
                        'created_by'      => Auth::id(),
                    ]);

                    $bankAccount->update([
                        'current_balance' => round((float) $newBankBal, 2),
                    ]);
                }
            }
        });
    }

    /**
     * @deprecated Use addPayment() com o valor total para manter o histórico.
     */
    public function payReceipt(CustomerBillingReceipt $receipt, array $data): void
    {
        $remaining = (string) $receipt->remaining_amount;
        $this->addPayment($receipt, array_merge($data, ['amount' => $remaining]));
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Validação de distribuições
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Verifica se uma lista de IDs de distribuições pode ser vinculada a um
     * novo comprovante de cliente (não estão em comprovante PAGO).
     *
     * @param  int[]  $deliveryIds
     * @return array{ valid: array<int>, blocked: array<int> }
     */
    public function validateDistributions(array $deliveryIds): array
    {
        $blocked = ProductionDelivery::whereIn('id', $deliveryIds)
            ->whereNotNull('billing_receipt_id')
            ->whereHas(
                'billingReceipt',
                fn ($q) => $q->where('status', CustomerReceiptStatus::PAID->value)
            )
            ->pluck('id')
            ->values()
            ->all();

        $valid = array_values(array_diff($deliveryIds, $blocked));

        return compact('valid', 'blocked');
    }
}
