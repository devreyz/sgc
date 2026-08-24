<?php

namespace App\Services;

use App\Enums\CashMovementType;
use App\Enums\CustomerReceiptStatus;
use App\Models\BankAccount;
use App\Models\CashMovement;
use App\Models\CustomerBillingReceipt;
use App\Models\CustomerProjectFee;
use App\Models\CustomerReceiptPayment;
use App\Models\ProductionDelivery;
use App\Models\SalesProject;
use App\Support\FinancialAmount;
use App\Services\Accounting\BillingAuthorizationValidityService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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
        $customerFees = CustomerProjectFee::query()
            ->where('tenant_id', $project->tenant_id)
            ->where('sales_project_id', $project->id)
            ->where('active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $totalGross = '0';
        $totalFees = '0';
        $totalNet = '0';
        $totalDiscounts = '0';
        $totalAccruals = '0';
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
                    'net' => $gross,
                    'total_fee' => '0',
                    'fees' => [],
                    'total_discounts' => '0',
                    'total_accruals' => '0',
                ];
            } else {
                $result = $this->calculator->calculateWithFees($project, $gross, $customerFees);
            }

            $totalGross = bcadd($totalGross, $gross, 8);
            $totalFees = bcadd($totalFees, $result['total_fee'], 8);
            $totalNet = bcadd($totalNet, $result['net'], 8);
            $netFee = (string) ($result['total_fee'] ?? '0');
            $discounts = (string) ($result['total_discounts'] ?? (bccomp($netFee, '0', 8) >= 0 ? $netFee : '0'));
            $accruals = (string) ($result['total_accruals'] ?? (bccomp($netFee, '0', 8) < 0 ? bcsub('0', $netFee, 8) : '0'));
            $totalDiscounts = bcadd($totalDiscounts, $discounts, 8);
            $totalAccruals = bcadd($totalAccruals, $accruals, 8);

            if ($feeDetails === null) {
                $feeDetails = $result['fees'];
            }
        }

        $feeSnapshot = [
            'fees' => $feeDetails ?? [],
            'total_discounts' => $totalDiscounts,
            'total_accruals' => $totalAccruals,
            'total_fee' => $totalFees,
            'distribution_count' => $distributions->count(),
            'fee_source' => $customerFees->isNotEmpty() ? 'customer_project_fees' : 'no_fees',
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
        $ids = $distributions->pluck('id')->filter()->map(fn ($id) => (int) $id)->unique()->sort()->values()->all();

        if (empty($ids)) {
            throw new \RuntimeException('Nenhuma distribuição selecionada para o comprovante.');
        }

        DB::transaction(function () use ($receipt, $project, $ids) {
            $lockedReceipt = CustomerBillingReceipt::withoutGlobalScopes()
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
                    'billing_receipt_id',
                ]);

            // ── 2. Validação DENTRO da transação (após lock, antes do UPDATE) ──
            if ($locked->count() !== count($ids)) {
                throw new \RuntimeException('Uma ou mais distribuicoes selecionadas nao existem neste tenant.');
            }

            $this->integrity->assertCommon($locked, $project, (int) $receipt->tenant_id);
            $this->integrity->assertCustomerRecipient(
                $locked,
                (int) $receipt->tenant_id,
                $lockedReceipt->customer_id ? (int) $lockedReceipt->customer_id : null,
                $lockedReceipt->organization_id ? (int) $lockedReceipt->organization_id : null,
            );

            $alreadyClaimed = $locked->filter(function ($d) use ($receipt) {
                // Já vinculada a OUTRO comprovante (não o atual)
                return ! is_null($d->billing_receipt_id)
                    && $d->billing_receipt_id !== $receipt->id;
            });

            if ($alreadyClaimed->isNotEmpty()) {
                throw new \RuntimeException(
                    'As distribuicoes a seguir ja pertencem a outra cobranca de cliente: '
                    .$alreadyClaimed->pluck('id')->implode(', ')
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
                'status' => CustomerReceiptStatus::PENDING_PAYMENT->value,
            ]);

            // ── 4. Vincular distribuições (apenas as que ainda não têm vínculo ativo) ─
            $freeIds = $locked
                ->filter(fn ($d) => is_null($d->billing_receipt_id) || $d->billing_receipt_id === $receipt->id)
                ->pluck('id')
                ->all();

            if (! empty($freeIds)) {
                $affected = ProductionDelivery::whereIn('id', $freeIds)
                    ->update(['billing_receipt_id' => $lockedReceipt->id]);

                // ── 5. Verificação de integridade: detecta race condition residual ──
                if ($affected !== count($freeIds)) {
                    throw new \RuntimeException(
                        'Race condition detectada: apenas '.$affected.' de '.count($freeIds)
                        .' distribuições foram vinculadas. Tente novamente.'
                    );
                }
            }
        }, 5);

        try {
            $freshReceipt = CustomerBillingReceipt::withoutGlobalScopes()
                ->where('tenant_id', $receipt->tenant_id)
                ->findOrFail($receipt->id);
            app(BillingAuthorizationValidityService::class)->invalidateIfChanged(
                $freshReceipt,
                Auth::user(),
                'A cobrança foi recomposta a partir de suas distribuições.'
            );
        } catch (\Throwable $exception) {
            Log::error('Falha ao verificar autorização após recompor cobrança.', [
                'tenant_id' => $receipt->tenant_id,
                'receipt_id' => $receipt->id,
                'error' => $exception->getMessage(),
            ]);
        }
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
     *  4. Mantem o estado do recebimento no comprovante e nas parcelas, sem reutilizar billing_status
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
     *   operation_key: string,
     *   payment_date: string,
     *   payment_method: string|null,
     *   bank_account_id: int|null,
     *   document_number: string|null,
     *   notes: string|null,
     * } $data
     */
    public function addPayment(CustomerBillingReceipt $receipt, array $data): void
    {
        if (session()->has('tenant_id') && (int) session('tenant_id') !== (int) $receipt->tenant_id) {
            throw new \RuntimeException('A cobranca nao pertence ao tenant atual.');
        }

        $amount = FinancialAmount::cents($data['amount'] ?? 0);
        if (bccomp($amount, '0', 2) <= 0) {
            throw new \RuntimeException('O valor do recebimento deve ser maior que zero.');
        }
        $operationKey = strtolower(trim((string) ($data['operation_key'] ?? '')));
        if (! Str::isUuid($operationKey)) {
            throw new \RuntimeException('A chave tecnica desta operacao e invalida. Reabra o formulario e tente novamente.');
        }
        $paymentDate = $data['payment_date'] ?? now()->toDateString();

        DB::transaction(function () use ($receipt, $data, $amount, $operationKey, $paymentDate) {
            $lockedReceipt = CustomerBillingReceipt::withoutGlobalScopes()
                ->where('tenant_id', $receipt->tenant_id)
                ->lockForUpdate()
                ->findOrFail($receipt->id);

            $existingOperation = CustomerReceiptPayment::withoutGlobalScopes()
                ->where('tenant_id', $lockedReceipt->tenant_id)
                ->where('operation_key', $operationKey)
                ->lockForUpdate()
                ->first();

            if ($existingOperation) {
                if ((int) $existingOperation->customer_billing_receipt_id === (int) $lockedReceipt->id
                    && bccomp((string) $existingOperation->amount, $amount, 2) === 0) {
                    return;
                }

                throw new \RuntimeException('Esta chave tecnica ja foi usada em outra operacao financeira.');
            }

            if (! in_array($lockedReceipt->status, [
                CustomerReceiptStatus::PENDING_PAYMENT,
                CustomerReceiptStatus::PARTIALLY_PAID,
            ], true)) {
                throw new \RuntimeException('A cobranca nao esta disponivel para recebimento.');
            }

            $netValue = FinancialAmount::cents($lockedReceipt->total_net);
            if (bccomp($netValue, '0', 2) <= 0) {
                throw new \RuntimeException('A cobranca nao possui valor liquido congelado.');
            }

            $paymentRows = CustomerReceiptPayment::query()
                ->where('tenant_id', $lockedReceipt->tenant_id)
                ->where('customer_billing_receipt_id', $lockedReceipt->id)
                ->lockForUpdate()
                ->get(['id', 'amount', 'document_number']);
            $paid = $paymentRows->reduce(
                fn (string $total, $payment): string => bcadd($total, FinancialAmount::cents($payment->amount), 2),
                '0.00',
            );
            $remaining = FinancialAmount::remaining($netValue, $paid);

            if (bccomp($amount, $remaining, 2) > 0) {
                throw new \RuntimeException(
                    'O valor informado excede o saldo restante de R$ '.number_format((float) $remaining, 2, ',', '.').'.'
                );
            }

            $documentNumber = trim((string) ($data['document_number'] ?? ''));
            if ($documentNumber !== '' && $paymentRows->contains(
                fn ($payment) => trim((string) $payment->document_number) === $documentNumber
            )) {
                throw new \RuntimeException('Este documento de recebimento ja foi registrado nesta cobranca.');
            }

            $payment = CustomerReceiptPayment::create([
                'tenant_id' => $lockedReceipt->tenant_id,
                'customer_billing_receipt_id' => $lockedReceipt->id,
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
            $newPaid = bcadd($paid, $amount, 2);
            $isFull = bccomp($newPaid, $netValue, 2) >= 0;

            $updateData = [
                'amount_paid' => round((float) $newPaid, 2),
                'status' => $isFull
                    ? CustomerReceiptStatus::PAID->value
                    : CustomerReceiptStatus::PARTIALLY_PAID->value,
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

            $recipientName = $lockedReceipt->recipient_name;
            $projectTitle = optional($lockedReceipt->project)->title ?? 'Projeto';

            // ── Movimento de caixa (entrada proporcional) ──────────────────
            if (! empty($data['bank_account_id'])) {
                $bankAccount = BankAccount::withoutGlobalScopes()
                    ->where('tenant_id', $lockedReceipt->tenant_id)
                    ->lockForUpdate()
                    ->find($data['bank_account_id']);
                if (! $bankAccount) {
                    throw new \RuntimeException('A conta informada nao pertence ao tenant desta cobranca.');
                }

                $currentBankBal = (string) ($bankAccount->current_balance ?? 0);
                $newBankBal = bcadd($currentBankBal, $amount, 8);

                CashMovement::create([
                    'tenant_id' => $lockedReceipt->tenant_id,
                    'type' => CashMovementType::INCOME,
                    'amount' => round((float) $amount, 2),
                    'balance_after' => round((float) $newBankBal, 2),
                    'description' => "Recebimento de cliente — {$recipientName} — {$projectTitle} — {$lockedReceipt->formatted_number}",
                    'movement_date' => $paymentDate,
                    'bank_account_id' => $data['bank_account_id'],
                    'reference_type' => CustomerReceiptPayment::class,
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

    /**
     * @deprecated Use addPayment() com o valor total para manter o histórico.
     */
    public function payReceipt(CustomerBillingReceipt $receipt, array $data): void
    {
        $existingAmount = CustomerReceiptPayment::withoutGlobalScopes()
            ->where('tenant_id', $receipt->tenant_id)
            ->where('operation_key', $data['operation_key'] ?? '')
            ->where('customer_billing_receipt_id', $receipt->id)
            ->value('amount');
        $remaining = (string) ($existingAmount ?? $receipt->remaining_amount);
        $this->addPayment($receipt, array_merge($data, ['amount' => $remaining]));
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Validação de distribuições
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Verifica se uma lista de IDs de distribuições pode ser vinculada a um
     * novo comprovante de cliente (nao estao vinculadas a outra cobranca).
     *
     * @param  int[]  $deliveryIds
     * @return array{ valid: array<int>, blocked: array<int> }
     */
    public function validateDistributions(array $deliveryIds): array
    {
        $blocked = ProductionDelivery::whereIn('id', $deliveryIds)
            ->whereNotNull('billing_receipt_id')
            ->pluck('id')
            ->values()
            ->all();

        $valid = array_values(array_diff($deliveryIds, $blocked));

        return compact('valid', 'blocked');
    }
}
