<?php

namespace App\Services;

use App\Enums\BillingStatus;
use App\Models\Associate;
use App\Models\DistributionBilling;
use App\Models\ProductionDelivery;
use App\Models\Revenue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DistributionBillingService
{
    public function __construct(
        private readonly ProjectFinancialCalculator $calculator
    ) {}

    /**
     * Fatura um conjunto de distribuições aprovadas e não faturadas.
     *
     * Fluxo:
     *   1. Valida que todas são distribuições (parent_delivery_id != NULL)
     *   2. Valida que todas estão aprovadas e com billing_status = unbilled
     *   3. Congela os valores no momento do faturamento
     *   4. Cria DistributionBilling (lote)
     *   5. Para cada associado no lote: cria AssociateLedger (crédito) + Revenue (taxa)
     *   6. Marca as distribuições como billed + vincula ao lote
     *   7. Tudo em DB::transaction
     *
     * @param  array  $distributionIds  IDs de ProductionDelivery (distribuições)
     * @param  array  $options  ['billing_date' => date, 'reference' => string, 'notes' => string,
     *                           'period_start' => date, 'period_end' => date]
     * @return DistributionBilling
     *
     * @throws \InvalidArgumentException  Se algum ID não for válido
     * @throws \RuntimeException          Se já existir faturamento para algum ID
     */
    public function billDistributions(array $distributionIds, array $options = []): DistributionBilling
    {
        $distributions = ProductionDelivery::withoutGlobalScopes()
            ->whereIn('id', $distributionIds)
            ->get();

        $this->validate($distributions, $distributionIds);

        return DB::transaction(function () use ($distributions, $options) {
            $firstDistribution = $distributions->first();
            $project   = $firstDistribution->salesProject;
            $tenantId  = $firstDistribution->tenant_id;
            $billingDate = $options['billing_date'] ?? now()->toDateString();

            // Calcular totais usando o motor financeiro central
            [$totalGross, $totalFee, $totalNet, $feeSnapshot] =
                $this->calculateTotals($distributions, $project);

            $groupedByAssociate = $distributions->groupBy('associate_id');

            if ($groupedByAssociate->count() === 1) {
                $billing = $this->createBillingRecord(
                    tenantId:     $tenantId,
                    projectId:    $project->id,
                    associateId:  $distributions->first()->associate_id,
                    distributions: $distributions,
                    totalGross:   $totalGross,
                    totalFee:     $totalFee,
                    totalNet:     $totalNet,
                    feeSnapshot:  $feeSnapshot,
                    billingDate:  $billingDate,
                    options:      $options
                );

                $this->processFinancials($distributions, $billing, $project);
            } else {
                $firstBilling = null;
                foreach ($groupedByAssociate as $associateId => $assocDistributions) {
                    [$aGross, $aFee, $aNet, $aSnap] =
                        $this->calculateTotals($assocDistributions, $project);

                    $billing = $this->createBillingRecord(
                        tenantId:     $tenantId,
                        projectId:    $project->id,
                        associateId:  $associateId,
                        distributions: $assocDistributions,
                        totalGross:   $aGross,
                        totalFee:     $aFee,
                        totalNet:     $aNet,
                        feeSnapshot:  $aSnap,
                        billingDate:  $billingDate,
                        options:      $options
                    );
                    $this->processFinancials($assocDistributions, $billing, $project);
                    $firstBilling = $firstBilling ?? $billing;
                }

                return $firstBilling;
            }

            return $billing;
        });
    }

    // -------------------------------------------------------------------------
    //  PRIVATE HELPERS
    // -------------------------------------------------------------------------

    private function validate(Collection $distributions, array $requestedIds): void
    {
        if ($distributions->count() !== count($requestedIds)) {
            $found = $distributions->pluck('id')->toArray();
            $missing = array_diff($requestedIds, $found);
            throw new \InvalidArgumentException(
                'Distribuições não encontradas: ' . implode(', ', $missing)
            );
        }

        foreach ($distributions as $dist) {
            if (is_null($dist->parent_delivery_id)) {
                throw new \InvalidArgumentException(
                    "ID #{$dist->id} é uma recepção, não uma distribuição. Somente distribuições podem ser faturadas."
                );
            }

            if ($dist->status->value !== 'approved') {
                throw new \InvalidArgumentException(
                    "Distribuição #{$dist->id} não está aprovada (status: {$dist->status->value}). Aprove antes de faturar."
                );
            }

            if ($dist->billing_status !== BillingStatus::UNBILLED) {
                throw new \RuntimeException(
                    "Distribuição #{$dist->id} já foi faturada (billing_status: {$dist->billing_status->value})."
                );
            }
        }

        // Todas devem pertencer ao mesmo projeto
        $projectIds = $distributions->pluck('sales_project_id')->unique();
        if ($projectIds->count() > 1) {
            throw new \InvalidArgumentException(
                'Todas as distribuições devem pertencer ao mesmo projeto. Projetos encontrados: ' . $projectIds->implode(', ')
            );
        }
    }

    /**
     * Calcula totais usando o motor financeiro central.
     * Retorna [totalGross, totalFee, totalNet, feeSnapshot].
     */
    private function calculateTotals(Collection $distributions, $project): array
    {
        $totalGross = '0';
        $totalFee   = '0';
        $totalNet   = '0';
        $snapshotFees = [];

        foreach ($distributions as $dist) {
            $gross    = (string) ($dist->gross_value ?? bcmul((string) $dist->quantity, (string) $dist->unit_price, 8));
            $result   = $this->calculator->calculate($project, $gross);
            $fee      = $result['total_fee'];
            $net      = $result['net'];

            $totalGross = bcadd($totalGross, $gross, 8);
            $totalFee   = bcadd($totalFee, $fee, 8);
            $totalNet   = bcadd($totalNet, $net, 8);

            // Acumula fees para snapshot (usa a última iteração como referência estrutural)
            $snapshotFees = $result['fees'];
        }

        // Snapshot: estrutura das taxas + totais acumulados
        $snapshot = [
            'fees'            => $snapshotFees,
            'total_discounts' => bcadd('0', $totalFee, 8), // simplificação: total_fee = discounts - accruals
            'total_accruals'  => '0',
            'total_fee'       => $totalFee,
        ];

        return [$totalGross, $totalFee, $totalNet, $snapshot];
    }

    private function createBillingRecord(
        int $tenantId,
        int $projectId,
        int $associateId,
        Collection $distributions,
        string $totalGross,
        string $totalFee,
        string $totalNet,
        array $feeSnapshot,
        string $billingDate,
        array $options
    ): DistributionBilling {
        return DistributionBilling::create([
            'tenant_id'           => $tenantId,
            'sales_project_id'    => $projectId,
            'associate_id'        => $associateId,
            'reference'           => $options['reference'] ?? null,
            'billing_date'        => $billingDate,
            'period_start'        => $options['period_start'] ?? null,
            'period_end'          => $options['period_end'] ?? null,
            'total_gross'         => $totalGross,
            'total_admin_fee'     => $totalFee,
            'total_net'           => $totalNet,
            'total_distributions' => $distributions->count(),
            'fee_snapshot'        => $feeSnapshot,
            'notes'               => $options['notes'] ?? null,
            'created_by'          => Auth::id(),
        ]);
    }

    private function processFinancials(Collection $distributions, DistributionBilling $billing, $project): void
    {
        foreach ($distributions as $dist) {
            $gross  = (string) ($dist->gross_value ?? bcmul((string) $dist->quantity, (string) $dist->unit_price, 8));
            $result = $this->calculator->calculate($project, $gross);
            $fee    = $result['total_fee'];
            $net    = $result['net'];

            // Congelar valores financeiros na distribuição (snapshot imutável)
            $dist->updateQuietly([
                'admin_fee_amount'         => $fee,
                'admin_fee_percentage'     => $result['admin_fee_percentage_eff'],
                'net_value'                => $net,
            ]);

            $associate    = $dist->associate;
            $projectTitle = $project->title ?? 'Avulsa';

            // ── Receita da cooperativa (taxa administrativa) ─────────────
            // Crédito financeiro do ASSOCIADO NÃO é criado aqui.
            // Segue o fluxo: Comprovante → Pagamento → 1 crédito único (AssociateReceiptService).
            if (bccomp($fee, '0', 4) > 0) {
                Revenue::create([
                    'description'      => "Taxa - {$dist->product->name} - {$associate->display_name}",
                    'amount'           => $fee,
                    'date'             => $billing->billing_date->toDateString(),
                    'revenueable_type' => ProductionDelivery::class,
                    'revenueable_id'   => $dist->id,
                    'status'           => 'pending',
                    'created_by'       => Auth::id(),
                ]);
            }

            // Marcar distribuição como faturada
            $dist->updateQuietly([
                'billing_status'          => BillingStatus::BILLED,
                'distribution_billing_id' => $billing->id,
            ]);
        }
    }
}
