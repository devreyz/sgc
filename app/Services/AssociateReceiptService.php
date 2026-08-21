<?php

namespace App\Services;

use App\Enums\CashMovementType;
use App\Enums\LedgerCategory;
use App\Enums\LedgerType;
use App\Enums\ReceiptStatus;
use App\Jobs\SyncAssociateReceiptToDrive;
use App\Models\AssociateLedger;
use App\Models\AssociateReceipt;
use App\Models\AssociateReceiptPayment;
use App\Models\BankAccount;
use App\Models\CashMovement;
use App\Models\ProductionDelivery;
use App\Models\SalesProject;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
    private readonly FinancialDistributionInvariantService $integrity;

    public function __construct(
        private readonly ProjectFinancialCalculator $calculator,
        ?FinancialDistributionInvariantService $integrity = null,
    ) {
        $this->integrity = $integrity ?? new FinancialDistributionInvariantService;
    }

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
        $totalFees = '0';
        $totalNet = '0';
        $totalDiscounts = '0';
        $totalAccruals = '0';
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
            $totalFees = bcadd($totalFees, $result['total_fee'], 8);
            $totalNet = bcadd($totalNet, $result['net'], 8);
            $netFee = (string) ($result['total_fee'] ?? '0');
            $discounts = (string) ($result['total_discounts'] ?? (bccomp($netFee, '0', 8) >= 0 ? $netFee : '0'));
            $accruals = (string) ($result['total_accruals'] ?? (bccomp($netFee, '0', 8) < 0 ? bcsub('0', $netFee, 8) : '0'));
            $totalDiscounts = bcadd($totalDiscounts, $discounts, 8);
            $totalAccruals = bcadd($totalAccruals, $accruals, 8);

            // Estrutura das taxas é a mesma para todas as distribuições do projeto
            if ($feeDetails === null) {
                $feeDetails = $result['fees'];
            }
        }

        // fee_snapshot escalonado para exibição (percentuais já congelados)
        $feeSnapshot = [
            'fees' => $feeDetails ?? [],
            'total_discounts' => $totalDiscounts,
            'total_accruals' => $totalAccruals,
            'total_fee' => $totalFees,
            'distribution_count' => $distributions->count(),
        ];

        return [
            'total_gross' => $totalGross,
            'total_fees' => $totalFees,
            'total_net' => $totalNet,
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
        $ids = $distributions->pluck('id')->filter()->map(fn ($id) => (int) $id)->unique()->sort()->values()->all();

        if (empty($ids)) {
            throw new \RuntimeException('Nenhuma distribuição selecionada para o comprovante.');
        }

        DB::transaction(function () use ($receipt, $project, $ids) {
            $lockedReceipt = AssociateReceipt::withoutGlobalScopes()
                ->where('tenant_id', $receipt->tenant_id)
                ->where('sales_project_id', $receipt->sales_project_id)
                ->lockForUpdate()
                ->findOrFail($receipt->id);

            $this->integrity->assertProjectContext(
                $project,
                (int) $lockedReceipt->tenant_id,
                (int) $lockedReceipt->sales_project_id,
            );

            // ── 1. Pessimistic lock: bloqueia as linhas para escrita exclusiva ──
            $locked = ProductionDelivery::withoutGlobalScopes()
                ->where('tenant_id', $receipt->tenant_id)
                ->whereIn('id', $ids)
                ->orderBy('id')
                ->lockForUpdate()
                ->get([
                    'id',
                    'parent_delivery_id',
                    'tenant_id',
                    'sales_project_id',
                    'associate_id',
                    'customer_id',
                    'product_id',
                    'quantity',
                    'unit_price',
                    'gross_value',
                    'status',
                    'paid',
                    'associate_receipt_id',
                ]);

            // ── 2. Validação DENTRO da transação (após lock, antes do UPDATE) ──
            if ($locked->count() !== count($ids)) {
                throw new \RuntimeException('Uma ou mais distribuicoes selecionadas nao existem mais. Atualize a pagina e tente novamente.');
            }

            $this->integrity->assertCommon($locked, $project, (int) $receipt->tenant_id);
            $this->integrity->assertAssociate($locked, (int) $lockedReceipt->associate_id);

            $alreadyInAnotherReceipt = $locked->filter(function ($d) use ($receipt) {
                return ! is_null($d->associate_receipt_id)
                    && (int) $d->associate_receipt_id !== (int) $receipt->id;
            });

            if ($alreadyInAnotherReceipt->isNotEmpty()) {
                throw new \RuntimeException(
                    'As distribuicoes a seguir ja estao em outro comprovante ativo: '
                    .$alreadyInAnotherReceipt->pluck('id')->implode(', ')
                );
            }

            $paidOrLocked = $locked->filter(fn ($d) => $d->paid);

            if ($paidOrLocked->isNotEmpty()) {
                throw new \RuntimeException(
                    'As distribuicoes a seguir ja foram pagas ao membro: '
                    .$paidOrLocked->pluck('id')->implode(', ')
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
                    .$blockedByPaidAssociate->pluck('id')->implode(', ')
                );
            }

            $snapshot = $this->computeSnapshot($locked, $project);

            // ── 3. Snapshot no comprovante ─────────────────────────────────────
            $lockedReceipt->updateQuietly([
                'total_gross' => $snapshot['total_gross'],
                'total_fees' => $snapshot['total_fees'],
                'total_net' => $snapshot['total_net'],
                'fee_snapshot' => $snapshot['fee_snapshot'],
                'delivery_ids' => $ids,
                'status' => ReceiptStatus::PENDING_PAYMENT->value,
                'obsolete_at' => null,
                'obsolete_by' => null,
                'obsolete_reason' => null,
            ]);

            // ── 4. Vincular distribuições ──────────────────────────────────────
            $freeIds = $locked
                ->filter(fn ($d) => is_null($d->associate_receipt_id) || $d->associate_receipt_id === $receipt->id)
                ->pluck('id')
                ->all();

            if (! empty($freeIds)) {
                $affected = ProductionDelivery::whereIn('id', $freeIds)
                    ->update(['associate_receipt_id' => $lockedReceipt->id]);

                // ── 5. Verificação de integridade: detecta race condition residual ──
                if ($affected !== count($freeIds)) {
                    throw new \RuntimeException(
                        'Race condition detectada: apenas '.$affected.' de '.count($freeIds)
                        .' distribuições foram vinculadas. Tente novamente.'
                    );
                }
            }
        }, 5);

        SyncAssociateReceiptToDrive::dispatch($receipt->id)->afterCommit();
    }

    /**
     * Substitui integralmente as distribuicoes de um comprovante operacional.
     *
     * O array delivery_ids e a FK associate_receipt_id permanecem sempre
     * representando o mesmo conjunto.
     *
     * @param  array<int, int|string>  $distributionIds
     * @return array{added: array<int>, removed: array<int>, selected: array<int>}
     */
    public function replaceDistributions(
        AssociateReceipt $receipt,
        array $distributionIds,
        SalesProject $project,
        bool $markObsolete = false,
        string $obsoleteReason = 'As distribuicoes do comprovante foram alteradas.'
    ): array {
        $selectedIds = collect($distributionIds)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($selectedIds->isEmpty()) {
            throw new \RuntimeException('Selecione ao menos uma distribuicao para o comprovante.');
        }

        $result = DB::transaction(function () use (
            $receipt,
            $selectedIds,
            $project,
            $markObsolete,
            $obsoleteReason
        ) {
            $lockedReceipt = AssociateReceipt::query()
                ->where('tenant_id', $receipt->tenant_id)
                ->where('sales_project_id', $receipt->sales_project_id)
                ->where('associate_id', $receipt->associate_id)
                ->lockForUpdate()
                ->findOrFail($receipt->id);

            if (! $lockedReceipt->canBeOperationallyUpdated()) {
                throw new \RuntimeException(
                    'Este comprovante esta faturado, pago ou possui bloqueio financeiro e nao pode ser alterado.'
                );
            }

            $currentIds = ProductionDelivery::query()
                ->where('tenant_id', $lockedReceipt->tenant_id)
                ->where('associate_receipt_id', $lockedReceipt->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values();

            $allIds = $currentIds->merge($selectedIds)->unique()->values();
            $lockedRows = ProductionDelivery::query()
                ->where('tenant_id', $lockedReceipt->tenant_id)
                ->whereIn('id', $allIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get([
                    'id',
                    'parent_delivery_id',
                    'tenant_id',
                    'sales_project_id',
                    'associate_id',
                    'customer_id',
                    'product_id',
                    'quantity',
                    'unit_price',
                    'gross_value',
                    'status',
                    'paid',
                    'associate_receipt_id',
                ]);

            if ($lockedRows->count() !== $allIds->count()) {
                throw new \RuntimeException(
                    'Uma ou mais distribuicoes foram removidas durante a edicao. Atualize a pagina e tente novamente.'
                );
            }

            $selectedRows = $lockedRows
                ->whereIn('id', $selectedIds->all())
                ->values();

            $invalid = $selectedRows->first(fn (ProductionDelivery $distribution) => ! is_null($distribution->associate_receipt_id)
                && (int) $distribution->associate_receipt_id !== (int) $lockedReceipt->id
            );

            if ($selectedRows->count() !== $selectedIds->count() || $invalid) {
                $id = $invalid?->id;
                throw new \RuntimeException(
                    $id
                        ? "A distribuicao #{$id} nao e valida ou ja pertence a outro comprovante."
                        : 'Uma ou mais distribuicoes selecionadas nao existem.'
                );
            }

            $this->integrity->assertProjectContext(
                $project,
                (int) $lockedReceipt->tenant_id,
                (int) $lockedReceipt->sales_project_id,
            );
            $this->integrity->assertCommon($selectedRows, $project, (int) $lockedReceipt->tenant_id);
            $this->integrity->assertAssociate($selectedRows, (int) $lockedReceipt->associate_id);

            $financiallyLocked = $lockedRows->first(fn (ProductionDelivery $distribution) => $distribution->paid);

            if ($financiallyLocked) {
                throw new \RuntimeException(
                    "A distribuicao #{$financiallyLocked->id} ja foi paga ao membro."
                );
            }

            $removedIds = $currentIds->diff($selectedIds)->values();
            $addedIds = $selectedIds->diff($currentIds)->values();

            if ($removedIds->isNotEmpty()) {
                ProductionDelivery::query()
                    ->where('tenant_id', $lockedReceipt->tenant_id)
                    ->where('associate_receipt_id', $lockedReceipt->id)
                    ->whereIn('id', $removedIds)
                    ->update(['associate_receipt_id' => null]);
            }

            $this->freezeReceipt($lockedReceipt, $selectedRows, $project);

            $lockedReceipt->forceFill([
                'delivery_ids' => $selectedIds->all(),
            ])->saveQuietly();

            if ($markObsolete) {
                $lockedReceipt->forceFill([
                    'status' => ReceiptStatus::OBSOLETE,
                    'obsolete_at' => now(),
                    'obsolete_by' => Auth::id(),
                    'obsolete_reason' => $obsoleteReason,
                ])->save();
            }

            return [
                'added' => $addedIds->all(),
                'removed' => $removedIds->all(),
                'selected' => $selectedIds->all(),
            ];
        }, 5);

        activity('associate_receipt')
            ->performedOn($receipt)
            ->causedBy(Auth::user())
            ->withProperties($result)
            ->log('Distribuicoes do comprovante sincronizadas');

        return $result;
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
     *  3. Na quitacao, marca como pagas apenas as distribuicoes vinculadas pela FK do lado do membro
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
        $existingAmount = AssociateReceiptPayment::withoutGlobalScopes()
            ->where('tenant_id', $receipt->tenant_id)
            ->where('operation_key', $data['operation_key'] ?? '')
            ->where('associate_receipt_id', $receipt->id)
            ->value('amount');

        $this->addPayment($receipt, array_merge($data, [
            'amount' => $existingAmount ?? $receipt->remaining_amount,
        ]));
    }

    /**
     * Registra um pagamento parcial ou total ao associado.
     *
     * @param  array{
     *   amount: float|string,
     *   operation_key: string,
     *   payment_date: string,
     *   payment_method: string|null,
     *   bank_account_id: int|null,
     *   document_number: string|null,
     *   notes: string|null,
     * } $data
     */
    public function addPayment(AssociateReceipt $receipt, array $data): void
    {
        if (session()->has('tenant_id') && (int) session('tenant_id') !== (int) $receipt->tenant_id) {
            throw new \RuntimeException('O comprovante nao pertence ao tenant atual.');
        }

        $amount = bcadd((string) round((float) ($data['amount'] ?? 0), 2), '0', 8);
        if (bccomp($amount, '0', 2) <= 0) {
            throw new \RuntimeException('O valor do pagamento deve ser maior que zero.');
        }
        $operationKey = strtolower(trim((string) ($data['operation_key'] ?? '')));
        if (! Str::isUuid($operationKey)) {
            throw new \RuntimeException('A chave tecnica desta operacao e invalida. Reabra o formulario e tente novamente.');
        }
        $paymentDate = $data['payment_date'] ?? now()->toDateString();

        DB::transaction(function () use ($receipt, $data, $amount, $operationKey, $paymentDate) {
            $lockedReceipt = AssociateReceipt::withoutGlobalScopes()
                ->where('tenant_id', $receipt->tenant_id)
                ->lockForUpdate()
                ->findOrFail($receipt->id);

            $existingOperation = AssociateReceiptPayment::withoutGlobalScopes()
                ->where('tenant_id', $lockedReceipt->tenant_id)
                ->where('operation_key', $operationKey)
                ->lockForUpdate()
                ->first();

            if ($existingOperation) {
                if ((int) $existingOperation->associate_receipt_id === (int) $lockedReceipt->id
                    && bccomp((string) $existingOperation->amount, $amount, 2) === 0) {
                    return;
                }

                throw new \RuntimeException('Esta chave tecnica ja foi usada em outra operacao financeira.');
            }

            if (! in_array($lockedReceipt->status, [ReceiptStatus::PENDING_PAYMENT, ReceiptStatus::PARTIALLY_PAID], true)) {
                throw new \RuntimeException('O comprovante nao esta disponivel para pagamento.');
            }

            $netValue = (string) ($lockedReceipt->total_net ?? 0);
            if (bccomp($netValue, '0', 8) <= 0) {
                throw new \RuntimeException('O comprovante nao possui valor liquido congelado.');
            }

            $paymentRows = AssociateReceiptPayment::query()
                ->where('tenant_id', $lockedReceipt->tenant_id)
                ->where('associate_receipt_id', $lockedReceipt->id)
                ->lockForUpdate()
                ->get(['id', 'amount', 'document_number']);
            $paid = (string) $paymentRows->sum(fn ($payment) => (float) $payment->amount);
            $remaining = bcsub($netValue, $paid, 8);

            if (bccomp($amount, $remaining, 2) > 0) {
                throw new \RuntimeException(
                    'O valor informado excede o saldo restante de R$ '.number_format((float) $remaining, 2, ',', '.').'.'
                );
            }

            $documentNumber = trim((string) ($data['document_number'] ?? ''));
            if ($documentNumber !== '' && $paymentRows->contains(
                fn ($payment) => trim((string) $payment->document_number) === $documentNumber
            )) {
                throw new \RuntimeException('Este documento de pagamento ja foi registrado neste comprovante.');
            }

            $payment = AssociateReceiptPayment::create([
                'tenant_id' => $lockedReceipt->tenant_id,
                'associate_receipt_id' => $lockedReceipt->id,
                'operation_key' => $operationKey,
                'amount' => round((float) $amount, 2),
                'payment_date' => $paymentDate,
                'payment_method' => $data['payment_method'] ?? null,
                'bank_account_id' => $data['bank_account_id'] ?? null,
                'document_number' => $data['document_number'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => Auth::id(),
            ]);

            // ── Atualizar amount_paid ──────────────────────────────────────
            $newPaid = bcadd($paid, $amount, 8);
            $isFull = bccomp($newPaid, $netValue, 2) >= 0;

            $updateData = [
                'amount_paid' => round((float) $newPaid, 2),
                'status' => $isFull
                    ? ReceiptStatus::PAID->value
                    : ReceiptStatus::PARTIALLY_PAID->value,
            ];

            if ($isFull) {
                $updateData['paid_at'] = now();
                $updateData['paid_by'] = Auth::id();
                $updateData['payment_method'] = $data['payment_method'] ?? null;
                $updateData['bank_account_id'] = $data['bank_account_id'] ?? null;
                $updateData['document_number'] = $data['document_number'] ?? null;
                $updateData['payment_notes'] = $data['notes'] ?? null;
            }
            $lockedReceipt->update($updateData);

            $associate = $lockedReceipt->associate;
            $projectTitle = optional($lockedReceipt->project)->title ?? 'Projeto';

            // ── Crédito no extrato do associado ────────────────────────────
            $lastEntry = AssociateLedger::withoutGlobalScopes()
                ->where('tenant_id', $lockedReceipt->tenant_id)
                ->where('associate_id', $associate->id)
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();
            $currentBal = (string) ($lastEntry?->balance_after ?? 0);
            $newBalance = bcadd($currentBal, $amount, 8);

            AssociateLedger::create([
                'tenant_id' => $lockedReceipt->tenant_id,
                'associate_id' => $associate->id,
                'type' => LedgerType::CREDIT,
                'amount' => round((float) $amount, 2),
                'balance_after' => round((float) $newBalance, 2),
                'description' => ($isFull ? '' : '[Parcial] ').
                    "Pagamento — {$projectTitle} — Comprovante {$lockedReceipt->formatted_number}",
                'notes' => $data['notes'] ?? null,
                'reference_type' => AssociateReceiptPayment::class,
                'reference_id' => $payment->id,
                'category' => LedgerCategory::PRODUCAO,
                'created_by' => Auth::id(),
                'transaction_date' => $paymentDate,
            ]);

            // ── Marcar distribuições como pagas no lado do membro (somente se quitado) ──
            if ($isFull) {
                ProductionDelivery::withoutGlobalScopes()
                    ->where('tenant_id', $lockedReceipt->tenant_id)
                    ->where('associate_receipt_id', $lockedReceipt->id)
                    ->update(['paid' => true, 'paid_date' => $paymentDate]);
            }

            // ── Movimento de caixa (saída proporcional) ────────────────────
            if (! empty($data['bank_account_id'])) {
                $bankAccount = BankAccount::withoutGlobalScopes()
                    ->where('tenant_id', $lockedReceipt->tenant_id)
                    ->lockForUpdate()
                    ->find($data['bank_account_id']);
                if (! $bankAccount) {
                    throw new \RuntimeException('A conta informada nao pertence ao tenant deste comprovante.');
                }

                $currentBankBal = (string) ($bankAccount->current_balance ?? 0);
                $newBankBal = bcsub($currentBankBal, $amount, 8);

                CashMovement::create([
                    'tenant_id' => $lockedReceipt->tenant_id,
                    'type' => CashMovementType::EXPENSE,
                    'amount' => round((float) $amount, 2),
                    'balance_after' => round((float) $newBankBal, 2),
                    'description' => "Pagamento de membro — {$associate->display_name} — {$projectTitle} — {$lockedReceipt->formatted_number}",
                    'movement_date' => $paymentDate,
                    'bank_account_id' => $data['bank_account_id'],
                    'reference_type' => AssociateReceiptPayment::class,
                    'reference_id' => $payment->id,
                    'payment_method' => $data['payment_method'] ?? null,
                    'document_number' => $data['document_number'] ?? null,
                    'notes' => $data['notes'] ?? null,
                    'created_by' => Auth::id(),
                ]);

                $bankAccount->update([
                    'current_balance' => round((float) $newBankBal, 2),
                ]);
            }
        }, 5);

        $receipt->refresh();
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
                    ->orWhere('paid', true);
            })
            ->pluck('id')
            ->values()
            ->all();

        $blockedAssociate = ProductionDelivery::whereIn('id', $deliveryIds)
            ->whereNotNull('associate_receipt_id')
            ->pluck('id')
            ->values()
            ->all();

        $blocked = array_values(array_unique(array_merge($blockedInvalid, $blockedAssociate)));
        $valid = array_values(array_diff($deliveryIds, $blocked));

        return compact('valid', 'blocked');
    }
}
