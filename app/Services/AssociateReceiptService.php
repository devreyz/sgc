<?php

namespace App\Services;

use App\Enums\BillingStatus;
use App\Enums\CashMovementType;
use App\Enums\LedgerCategory;
use App\Enums\LedgerType;
use App\Enums\ReceiptStatus;
use App\Models\AssociateLedger;
use App\Models\AssociateReceipt;
use App\Models\BankAccount;
use App\Models\CashMovement;
use App\Models\ProductionDelivery;
use App\Models\SalesProject;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Serviço central do fluxo financeiro de comprovantes.
 *
 * Fluxo: Distribuições → Comprovante (snapshot) → Pagamento → 1 Crédito Financeiro
 *
 * Responsabilidades:
 *  - computeSnapshot: calcula o resumo financeiro das distribuições
 *  - freezeReceipt:   congela o snapshot no comprovante e vincula as distribuições
 *  - payReceipt:      paga o comprovante, cria UM lançamento no extrato do associado
 */
class AssociateReceiptService
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
     * @return array{
     *   total_gross: string,
     *   total_fees: string,
     *   total_net: string,
     *   fee_snapshot: array,
     * }
     */
    public function computeSnapshot(Collection $distributions, SalesProject $project): array
    {
        $totalGross = '0';
        $totalFees  = '0';
        $totalNet   = '0';
        $feeDetails = null;

        foreach ($distributions as $dist) {
            // Valor bruto da distribuição: usa coluna gross_value se existir,
            // senão recalcula como qty * price
            $gross = $dist->gross_value ?? null;
            if ($gross === null || bccomp((string) $gross, '0', 8) <= 0) {
                $gross = bcmul((string) ($dist->quantity ?? 0), (string) ($dist->unit_price ?? 0), 8);
            }
            $gross = (string) $gross;

            $result = $this->calculator->calculate($project, $gross);

            $totalGross = bcadd($totalGross, $gross, 8);
            $totalFees  = bcadd($totalFees, $result['total_fee'], 8);
            $totalNet   = bcadd($totalNet, $result['net'], 8);

            // Estrutura das taxas é a mesma para todas as distribuições do projeto
            if ($feeDetails === null) {
                $feeDetails = $result['fees'];
            }
        }

        // fee_snapshot escalonado para exibição (percentuais já congelados)
        $feeSnapshot = [
            'fees'            => $feeDetails ?? [],
            'total_discounts' => $totalFees,
            'total_accruals'  => '0',
            'total_fee'       => $totalFees,
            'distribution_count' => $distributions->count(),
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
     * Deve ser chamado logo após a criação do AssociateReceipt.
     * As distribuições recebem associate_receipt_id = receipt.id.
     * O status muda para PENDING_PAYMENT.
     *
     * ── Segurança (Fase 4) ──────────────────────────────────────────────────
     * Aplica SELECT FOR UPDATE nas linhas antes de qualquer escrita.
     * Verifica o total de linhas afetadas e lança exceção em caso de
     * race condition (dois processos simultâneos tentando o mesmo lote).
     *
     * @throws \RuntimeException Se alguma distribuição já estiver em recibo PAGO
     *                           ou em comprovante de cliente pago, ou race condition.
     */
    public function freezeReceipt(
        AssociateReceipt $receipt,
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
                ->get([
                    'id',
                    'parent_delivery_id',
                    'tenant_id',
                    'sales_project_id',
                    'associate_id',
                    'customer_id',
                    'quantity',
                    'unit_price',
                    'paid',
                    'billing_status',
                    'associate_receipt_id',
                    'billing_receipt_id',
                ]);

            // ── 2. Validação DENTRO da transação (após lock, antes do UPDATE) ──
            if ($locked->count() !== count($ids)) {
                throw new \RuntimeException('Uma ou mais distribuicoes selecionadas nao existem mais. Atualize a pagina e tente novamente.');
            }

            $invalid = $locked->first(function ($d) use ($receipt) {
                return (int) $d->tenant_id !== (int) $receipt->tenant_id
                    || (int) $d->sales_project_id !== (int) $receipt->sales_project_id
                    || (int) $d->associate_id !== (int) $receipt->associate_id
                    || is_null($d->parent_delivery_id)
                    || is_null($d->customer_id)
                    || (float) ($d->quantity ?? 0) <= 0
                    || (float) ($d->unit_price ?? 0) <= 0;
            });

            if ($invalid) {
                throw new \RuntimeException('A distribuicao #' . $invalid->id . ' nao e valida para comprovante financeiro.');
            }

            $alreadyInAnotherReceipt = $locked->filter(function ($d) use ($receipt) {
                return ! is_null($d->associate_receipt_id)
                    && (int) $d->associate_receipt_id !== (int) $receipt->id;
            });

            if ($alreadyInAnotherReceipt->isNotEmpty()) {
                throw new \RuntimeException(
                    'As distribuicoes a seguir ja estao em outro comprovante ativo: '
                    . $alreadyInAnotherReceipt->pluck('id')->implode(', ')
                );
            }

            $paidOrLocked = $locked->filter(fn ($d) => $d->paid || $d->billing_status === BillingStatus::PAID);

            if ($paidOrLocked->isNotEmpty()) {
                throw new \RuntimeException(
                    'As distribuicoes a seguir ja foram pagas e nao podem entrar em novo comprovante: '
                    . $paidOrLocked->pluck('id')->implode(', ')
                );
            }

            $blockedByPaidAssociate = $locked->filter(function ($d) use ($receipt) {
                return ! is_null($d->associate_receipt_id)
                    && $d->associate_receipt_id !== $receipt->id
                    && AssociateReceipt::where('id', $d->associate_receipt_id)
                        ->where('status', ReceiptStatus::PAID->value)
                        ->exists();
            });

            if ($blockedByPaidAssociate->isNotEmpty()) {
                throw new \RuntimeException(
                    'As distribuições a seguir já estão em um comprovante de associado pago: '
                    . $blockedByPaidAssociate->pluck('id')->implode(', ')
                );
            }

            $blockedByPaidBilling = $locked->filter(function ($d) {
                return ! is_null($d->billing_receipt_id)
                    && \App\Models\CustomerBillingReceipt::where('id', $d->billing_receipt_id)
                        ->where('status', \App\Enums\CustomerReceiptStatus::PAID->value)
                        ->exists();
            });

            if ($blockedByPaidBilling->isNotEmpty()) {
                throw new \RuntimeException(
                    'As distribuições a seguir já foram recebidas pelo lado do cliente e não podem ser realocadas: '
                    . $blockedByPaidBilling->pluck('id')->implode(', ')
                );
            }

            // ── 3. Snapshot no comprovante ─────────────────────────────────────
            $receipt->updateQuietly([
                'total_gross'  => $snapshot['total_gross'],
                'total_fees'   => $snapshot['total_fees'],
                'total_net'    => $snapshot['total_net'],
                'fee_snapshot' => $snapshot['fee_snapshot'],
                'status'       => ReceiptStatus::PENDING_PAYMENT->value,
                'obsolete_at'  => null,
                'obsolete_by'  => null,
                'obsolete_reason' => null,
            ]);

            // ── 4. Vincular distribuições ──────────────────────────────────────
            $freeIds = $locked
                ->filter(fn ($d) => is_null($d->associate_receipt_id) || $d->associate_receipt_id === $receipt->id)
                ->pluck('id')
                ->all();

            if (! empty($freeIds)) {
                $affected = ProductionDelivery::whereIn('id', $freeIds)
                    ->update(['associate_receipt_id' => $receipt->id]);

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
    //  Pagar comprovante
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Paga um comprovante.
     *
     * O que acontece:
     *  1. Cria UM crédito no extrato financeiro do associado
     *  2. Marca o comprovante como PAID com os dados de pagamento
     *  3. Marca as distribuições vinculadas com billing_status = PAID
     *  4. Opcionalmente registra saída no Caixa (CashMovement)
     *
     * @param  array{
     *   payment_date: string|null,
     *   payment_method: string|null,
     *   bank_account_id: int|null,
     *   document_number: string|null,
     *   notes: string|null,
     * } $data
     */
    public function payReceipt(AssociateReceipt $receipt, array $data): void
    {
        if ($receipt->status === ReceiptStatus::PAID) {
            throw new \RuntimeException('Este comprovante já foi pago.');
        }

        $netValue = (string) ($receipt->total_net ?? 0);
        if (bccomp($netValue, '0', 8) <= 0) {
            throw new \RuntimeException(
                'O comprovante não possui valor líquido congelado. Regenere o PDF antes de pagar.'
            );
        }

        $associate    = $receipt->associate;
        $projectTitle = optional($receipt->project)->title ?? 'Projeto';
        $paymentDate  = $data['payment_date'] ?? now()->toDateString();

        DB::transaction(function () use ($receipt, $data, $netValue, $associate, $projectTitle, $paymentDate) {
            // ── Saldo atual do associado ──────────────────────────────────
            $lastEntry  = AssociateLedger::where('associate_id', $associate->id)
                ->orderByDesc('id')
                ->first();
            $currentBal = (string) ($lastEntry?->balance_after ?? 0);
            $newBalance = bcadd($currentBal, $netValue, 8);

            // ── 1 ÚNICO lançamento de crédito ─────────────────────────────
            AssociateLedger::create([
                'tenant_id'        => $receipt->tenant_id,
                'associate_id'     => $associate->id,
                'type'             => LedgerType::CREDIT,
                'amount'           => round((float) $netValue, 2),
                'balance_after'    => round((float) $newBalance, 2),
                'description'      => "Pagamento — {$projectTitle} — Comprovante {$receipt->formatted_number}",
                'notes'            => $data['notes'] ?? null,
                'reference_type'   => AssociateReceipt::class,
                'reference_id'     => $receipt->id,
                'category'         => LedgerCategory::PRODUCAO,
                'created_by'       => Auth::id(),
                'transaction_date' => $paymentDate,
            ]);

            // ── Marcar comprovante como pago ───────────────────────────────
            $receipt->update([
                'status'          => ReceiptStatus::PAID->value,
                'amount_paid'     => round((float) $netValue, 2),
                'paid_at'         => now(),
                'paid_by'         => Auth::id(),
                'payment_method'  => $data['payment_method'] ?? null,
                'bank_account_id' => $data['bank_account_id'] ?? null,
                'document_number' => $data['document_number'] ?? null,
                'payment_notes'   => $data['notes'] ?? null,
            ]);

            // ── Marcar distribuições como pagas ────────────────────────────
            $deliveryIds = $receipt->delivery_ids ?? [];
            if (! empty($deliveryIds)) {
                ProductionDelivery::whereIn('id', $deliveryIds)
                    ->update(['billing_status' => BillingStatus::PAID->value]);
            }

            // ── Movimento de caixa (se banco informado) ────────────────────
            if (! empty($data['bank_account_id'])) {
                $bankAccount = BankAccount::find($data['bank_account_id']);
                if ($bankAccount) {
                    $currentBankBal = (string) ($bankAccount->current_balance ?? 0);
                    $newBankBal     = bcsub($currentBankBal, $netValue, 8);

                    CashMovement::create([
                        'tenant_id'       => $receipt->tenant_id,
                        'type'            => CashMovementType::EXPENSE,
                        'amount'          => round((float) $netValue, 2),
                        'balance_after'   => round((float) $newBankBal, 2),
                        'description'     => "Pagamento associado — {$associate->name} — {$projectTitle}",
                        'movement_date'   => $paymentDate,
                        'bank_account_id' => $data['bank_account_id'],
                        'reference_type'  => AssociateReceipt::class,
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
     * Registra um pagamento parcial ou total ao associado.
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
    public function addPayment(AssociateReceipt $receipt, array $data): void
    {
        if ($receipt->status === ReceiptStatus::PAID) {
            throw new \RuntimeException('Este comprovante já foi integralmente pago.');
        }
        if (! in_array($receipt->status, [
            ReceiptStatus::PENDING_PAYMENT,
            ReceiptStatus::PARTIALLY_PAID,
        ])) {
            throw new \RuntimeException(
                'O comprovante precisa estar em Aguardando Pagamento para registrar um pagamento.'
            );
        }

        $netValue = (string) ($receipt->total_net ?? 0);
        if (bccomp($netValue, '0', 8) <= 0) {
            throw new \RuntimeException(
                'O comprovante não possui valor líquido congelado. Regenere o PDF antes de pagar.'
            );
        }

        $amount    = bcadd((string) round((float) $data['amount'], 2), '0', 8);
        $remaining = bcsub(
            bcadd($netValue, '0', 8),
            bcadd((string) round((float) ($receipt->amount_paid ?? 0), 2), '0', 8),
            8
        );
        if (bccomp($amount, '0', 2) <= 0) {
            throw new \RuntimeException('O valor do pagamento deve ser maior que zero.');
        }
        if (bccomp($amount, $remaining, 2) > 0) {
            throw new \RuntimeException(
                'O valor informado (R$ ' . number_format((float) $amount, 2, ',', '.') .
                ') excede o saldo restante (R$ ' . number_format((float) $remaining, 2, ',', '.') . ').'
            );
        }

        $associate    = $receipt->associate;
        $projectTitle = optional($receipt->project)->title ?? 'Projeto';
        $paymentDate  = $data['payment_date'] ?? now()->toDateString();

        DB::transaction(function () use ($receipt, $data, $amount, $netValue, $associate, $projectTitle, $paymentDate) {
            // ── Registrar parcela de pagamento ─────────────────────────────
            \App\Models\AssociateReceiptPayment::create([
                'tenant_id'             => $receipt->tenant_id,
                'associate_receipt_id'  => $receipt->id,
                'amount'                => round((float) $amount, 2),
                'payment_date'          => $paymentDate,
                'payment_method'        => $data['payment_method'] ?? null,
                'bank_account_id'       => $data['bank_account_id'] ?? null,
                'document_number'       => $data['document_number'] ?? null,
                'notes'                 => $data['notes'] ?? null,
                'created_by'            => Auth::id(),
            ]);

            // ── Atualizar amount_paid ──────────────────────────────────────
            $newPaid = bcadd((string) ($receipt->amount_paid ?? 0), $amount, 8);
            $isFull  = bccomp($newPaid, $netValue, 2) >= 0;

            $updateData = [
                'amount_paid' => round((float) $newPaid, 2),
                'status'      => $isFull
                    ? ReceiptStatus::PAID->value
                    : ReceiptStatus::PARTIALLY_PAID->value,
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

            // ── Crédito no extrato do associado ────────────────────────────
            $lastEntry  = AssociateLedger::where('associate_id', $associate->id)
                ->orderByDesc('id')->first();
            $currentBal = (string) ($lastEntry?->balance_after ?? 0);
            $newBalance = bcadd($currentBal, $amount, 8);

            AssociateLedger::create([
                'tenant_id'        => $receipt->tenant_id,
                'associate_id'     => $associate->id,
                'type'             => LedgerType::CREDIT,
                'amount'           => round((float) $amount, 2),
                'balance_after'    => round((float) $newBalance, 2),
                'description'      => ($isFull ? '' : '[Parcial] ') .
                    "Pagamento — {$projectTitle} — Comprovante {$receipt->formatted_number}",
                'notes'            => $data['notes'] ?? null,
                'reference_type'   => AssociateReceipt::class,
                'reference_id'     => $receipt->id,
                'category'         => LedgerCategory::PRODUCAO,
                'created_by'       => Auth::id(),
                'transaction_date' => $paymentDate,
            ]);

            // ── Marcar distribuições como pagas (somente se quitado) ───────
            if ($isFull) {
                $deliveryIds = $receipt->delivery_ids ?? [];
                if (! empty($deliveryIds)) {
                    ProductionDelivery::whereIn('id', $deliveryIds)
                        ->update(['billing_status' => BillingStatus::PAID->value]);
                }
            }

            // ── Movimento de caixa (saída proporcional) ────────────────────
            if (! empty($data['bank_account_id'])) {
                $bankAccount = BankAccount::find($data['bank_account_id']);
                if ($bankAccount) {
                    $currentBankBal = (string) ($bankAccount->current_balance ?? 0);
                    $newBankBal     = bcsub($currentBankBal, $amount, 8);

                    CashMovement::create([
                        'tenant_id'       => $receipt->tenant_id,
                        'type'            => CashMovementType::EXPENSE,
                        'amount'          => round((float) $amount, 2),
                        'balance_after'   => round((float) $newBankBal, 2),
                        'description'     => "Pagamento associado — {$associate->name} — {$projectTitle} — {$receipt->formatted_number}",
                        'movement_date'   => $paymentDate,
                        'bank_account_id' => $data['bank_account_id'],
                        'reference_type'  => AssociateReceipt::class,
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

    // ─────────────────────────────────────────────────────────────────────────
    //  Validação
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Verifica se uma lista de IDs de distribuições pode ser adicionada a um
     * novo comprovante (não estão vinculadas a um recibo PAGO).
     *
     * @return array{ valid: array<int>, blocked: array<int> }
     */
    public function validateDistributions(array $deliveryIds): array
    {
        // Bloqueadas pelo lado associado (comprovante de associado PAGO)
        $blockedInvalid = ProductionDelivery::whereIn('id', $deliveryIds)
            ->where(function ($query) {
                $query->whereNull('parent_delivery_id')
                    ->orWhereNull('customer_id')
                    ->orWhereNull('unit_price')
                    ->orWhere('unit_price', '<=', 0)
                    ->orWhere('quantity', '<=', 0)
                    ->orWhere('paid', true)
                    ->orWhere('billing_status', BillingStatus::PAID->value);
            })
            ->pluck('id')
            ->values()
            ->all();

        $blockedAssociate = ProductionDelivery::whereIn('id', $deliveryIds)
            ->whereNotNull('associate_receipt_id')
            ->pluck('id')
            ->values()
            ->all();

        // Bloqueadas pelo lado cliente (comprovante de cobrança PAGO)
        $blockedBilling = ProductionDelivery::whereIn('id', $deliveryIds)
            ->whereNotNull('billing_receipt_id')
            ->whereHas('billingReceipt', fn ($q) => $q->where('status', \App\Enums\CustomerReceiptStatus::PAID->value))
            ->pluck('id')
            ->values()
            ->all();

        $blocked = array_values(array_unique(array_merge($blockedInvalid, $blockedAssociate, $blockedBilling)));
        $valid   = array_values(array_diff($deliveryIds, $blocked));

        return compact('valid', 'blocked');
    }
}
