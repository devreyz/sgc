<?php

namespace App\Services;

use App\Enums\BillingStatus;
use App\Enums\LedgerCategory;
use App\Enums\LedgerType;
use App\Models\Associate;
use App\Models\AssociateLedger;
use App\Models\DistributionBilling;
use App\Models\ProductionDelivery;
use App\Models\ProjectFee;
use App\Models\Revenue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DistributionBillingService
{
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
            $project = $firstDistribution->salesProject;
            $tenantId = $firstDistribution->tenant_id;

            $billingDate = $options['billing_date'] ?? now()->toDateString();

            // Carregar taxas flexíveis ativas do projeto (se existirem, substituem admin_fee_percentage)
            $projectFees = ProjectFee::where('sales_project_id', $project->id)
                ->where('active', true)
                ->get();

            // Calcular totais
            [$totalGross, $totalFee, $totalNet] = $this->calculateTotals($distributions, $projectFees->isNotEmpty() ? $projectFees : null);

            // Criar o lote de faturamento (um por projeto nessa chamada)
            // Se as distribuições pertencem a múltiplos associados, agrupamos por associado
            $groupedByAssociate = $distributions->groupBy('associate_id');

            // Se há somente um associado, criamos um lote único; caso contrário, um por associado
            if ($groupedByAssociate->count() === 1) {
                $billing = $this->createBillingRecord(
                    tenantId: $tenantId,
                    projectId: $project->id,
                    associateId: $distributions->first()->associate_id,
                    distributions: $distributions,
                    totalGross: $totalGross,
                    totalFee: $totalFee,
                    totalNet: $totalNet,
                    billingDate: $billingDate,
                    options: $options
                );

                $this->processFinancials($distributions, $billing, $project, $projectFees);
            } else {
                // Múltiplos associados: cria um lote por associado
                $firstBilling = null;
                foreach ($groupedByAssociate as $associateId => $assocDistributions) {
                    [$aGross, $aFee, $aNet] = $this->calculateTotals($assocDistributions, $projectFees->isNotEmpty() ? $projectFees : null);
                    $billing = $this->createBillingRecord(
                        tenantId: $tenantId,
                        projectId: $project->id,
                        associateId: $associateId,
                        distributions: $assocDistributions,
                        totalGross: $aGross,
                        totalFee: $aFee,
                        totalNet: $aNet,
                        billingDate: $billingDate,
                        options: $options
                    );
                    $this->processFinancials($assocDistributions, $billing, $project, $projectFees);
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

    private function calculateTotals(Collection $distributions, ?Collection $projectFees = null): array
    {
        $totalGross = '0';
        $totalFee   = '0';
        $totalNet   = '0';

        foreach ($distributions as $dist) {
            $gross = (string) ($dist->gross_value ?? bcmul((string) $dist->quantity, (string) $dist->unit_price, 8));

            if ($projectFees && $projectFees->isNotEmpty()) {
                $fee = $this->applyProjectFees($projectFees, $gross);
            } else {
                $feePct = (string) ($dist->admin_fee_percentage ?? '0');
                $fee    = (string) ($dist->admin_fee_amount ?? bcmul($gross, bcdiv($feePct, '100', 8), 8));
            }

            $net = bcsub($gross, $fee, 8);

            $totalGross = bcadd($totalGross, $gross, 8);
            $totalFee   = bcadd($totalFee, $fee, 8);
            $totalNet   = bcadd($totalNet, $net, 8);
        }

        return [$totalGross, $totalFee, $totalNet];
    }

    /**
     * Soma todas as taxas ativas do projeto sobre um valor bruto.
     */
    private function applyProjectFees(Collection $fees, string $gross): string
    {
        $totalFee = '0';
        foreach ($fees as $fee) {
            $totalFee = bcadd($totalFee, $fee->calculate($gross), 8);
        }
        return $totalFee;
    }

    private function createBillingRecord(
        int $tenantId,
        int $projectId,
        int $associateId,
        Collection $distributions,
        string $totalGross,
        string $totalFee,
        string $totalNet,
        string $billingDate,
        array $options
    ): DistributionBilling {
        return DistributionBilling::create([
            'tenant_id'          => $tenantId,
            'sales_project_id'   => $projectId,
            'associate_id'       => $associateId,
            'reference'          => $options['reference'] ?? null,
            'billing_date'       => $billingDate,
            'period_start'       => $options['period_start'] ?? null,
            'period_end'         => $options['period_end'] ?? null,
            'total_gross'        => $totalGross,
            'total_admin_fee'    => $totalFee,
            'total_net'          => $totalNet,
            'total_distributions' => $distributions->count(),
            'notes'              => $options['notes'] ?? null,
            'created_by'         => Auth::id(),
        ]);
    }

    private function processFinancials(Collection $distributions, DistributionBilling $billing, $project, ?Collection $projectFees = null): void
    {
        foreach ($distributions as $dist) {
            $gross = (string) ($dist->gross_value ?? bcmul((string) $dist->quantity, (string) $dist->unit_price, 8));

            if ($projectFees && $projectFees->isNotEmpty()) {
                $fee  = $this->applyProjectFees($projectFees, $gross);
                $feeDesc = $projectFees->map(fn($f) => $f->name . ' (' . $f->getTypeLabel() . ')')->implode(', ');
                $feeNote = "Taxas aplicadas: {$feeDesc}";
            } else {
                $feeP    = (string) ($dist->admin_fee_percentage ?? $project->admin_fee_percentage ?? '0');
                $fee     = (string) ($dist->admin_fee_amount ?? bcmul($gross, bcdiv($feeP, '100', 8), 8));
                $feeNote = "Taxa admin ({$feeP}%)";
            }

            $net = bcsub($gross, $fee, 8);

            // Congelar valores na distribuição se ainda não calculados
            $dist->updateQuietly([
                'admin_fee_amount' => $fee,
                'net_value'        => $net,
            ]);

            $associate = $dist->associate;
            $currentBalance = (string) $this->getAssociateBalance($associate);
            $newBalance = bcadd($currentBalance, $net, 8);

            $projectTitle = $project->title ?? 'Avulsa';

            // Crédito no extrato do associado
            $ledger = AssociateLedger::create([
                'associate_id'    => $associate->id,
                'type'            => LedgerType::CREDIT,
                'amount'          => $net,
                'balance_after'   => $newBalance,
                'description'     => "Faturamento - {$dist->product->name} - Projeto: {$projectTitle}",
                'notes'           => "Valor bruto: R$ " . number_format((float) $gross, 2, ',', '.') .
                                     " | {$feeNote}: R$ " . number_format((float) $fee, 2, ',', '.') .
                                     " | Lote #" . $billing->id,
                'reference_type'  => ProductionDelivery::class,
                'reference_id'    => $dist->id,
                'category'        => LedgerCategory::PRODUCAO,
                'created_by'      => Auth::id(),
                'transaction_date' => $billing->billing_date->toDateString(),
            ]);

            // Taxa administrativa para a cooperativa
            Revenue::create([
                'description'      => "Taxa admin - {$dist->product->name} - {$associate->user->name}",
                'amount'           => $fee,
                'date'             => $billing->billing_date->toDateString(),
                'revenueable_type' => ProductionDelivery::class,
                'revenueable_id'   => $dist->id,
                'status'           => 'pending',
                'created_by'       => Auth::id(),
            ]);

            // Marcar distribuição como faturada
            $dist->updateQuietly([
                'billing_status'           => BillingStatus::BILLED,
                'distribution_billing_id'  => $billing->id,
            ]);
        }
    }

    private function getAssociateBalance(Associate $associate): float
    {
        $lastEntry = AssociateLedger::where('associate_id', $associate->id)
            ->orderByDesc('id')
            ->first();

        return $lastEntry ? (float) $lastEntry->balance_after : 0.0;
    }
}
