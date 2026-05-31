<?php

namespace App\Services;

use App\Models\ProjectFee;
use App\Models\SalesProject;
use Illuminate\Support\Collection;

/**
 * Motor central de cálculo financeiro de projetos.
 *
 * Este serviço é a ÚNICA fonte de verdade para aplicação de taxas,
 * descontos e acréscimos sobre um valor bruto. Todos os pontos do
 * sistema (distribuição, faturamento, comprovante, relatório, portal)
 * DEVEM usar este serviço. Nenhuma tela pode calcular por conta própria.
 *
 * Ordem de precedência das taxas:
 *   1. ProjectFee ativos do projeto (se houver) — ordenados por sort_order
 *   2. Fallback: admin_fee_percentage do projeto como desconto percentual único
 *
 * Natureza das taxas:
 *   'discount'  → reduz o valor líquido (ex: taxa adm, frete)
 *   'accrual'   → aumenta o valor líquido (ex: bônus de produção, incentivo)
 *
 * Fórmula:
 *   Líquido = Bruto - Σ(descontos) + Σ(acréscimos)
 */
class ProjectFinancialCalculator
{
    private const SCALE = 8;

    /**
     * Calcula o breakdown financeiro completo para um valor bruto.
     *
     * @param  SalesProject  $project
     * @param  string        $gross   Valor bruto com precisão BCMath (string)
     * @return array{
     *   gross: string,
     *   fees: list<array{id: int|null, name: string, type: string, nature: string, rate: string, amount: string, label: string}>,
     *   total_discounts: string,
     *   total_accruals: string,
     *   total_fee: string,
     *   net: string,
     *   admin_fee_percentage_eff: string,
     * }
     */
    public function calculate(SalesProject $project, string $gross): array
    {
        $projectFees = $this->loadFees($project);
        $adminPct    = (string) ($project->admin_fee_percentage ?? '0');
        $hasAdminFee = bccomp($adminPct, '0', 4) > 0;

        // Sem taxas de nenhum tipo → retorna zero
        if ($projectFees->isEmpty() && ! $hasAdminFee) {
            return $this->buildResult($gross, [], '0', '0');
        }

        // admin_fee_percentage é SEMPRE aplicado (se > 0) como taxa base.
        // ProjectFee são taxas ADICIONAIS sobre o bruto (frete, incentivo, etc.).
        // Fallback puro: sem ProjectFee e com admin_fee configurado.
        if ($projectFees->isEmpty()) {
            return $this->fallbackCalculation($project, $gross);
        }

        return $this->applyFees($gross, $projectFees, $project);
    }

    /**
     * Retorna apenas o total de taxas líquidas (desconto - acréscimo).
     * Atalho para casos onde só precisamos do fee total.
     */
    public function getTotalFee(SalesProject $project, string $gross): string
    {
        return $this->calculate($project, $gross)['total_fee'];
    }

    /**
     * Retorna o percentual efetivo de desconto líquido (para compatibilidade retroativa).
     * Calculado como: (total_fee / gross) * 100
     */
    public function getEffectivePercentage(SalesProject $project, string $gross): string
    {
        if (bccomp($gross, '0', self::SCALE) === 0) {
            return '0';
        }

        $totalFee = $this->getTotalFee($project, $gross);

        return bcmul(bcdiv($totalFee, $gross, self::SCALE), '100', self::SCALE);
    }

    /**
     * Snapshot adequado para armazenamento em JSON.
     * Contém apenas as taxas aplicadas — sem o gross (que fica nas colunas da tabela).
     */
    public function buildSnapshot(SalesProject $project, string $gross): array
    {
        $result = $this->calculate($project, $gross);

        return [
            'fees'             => $result['fees'],
            'total_discounts'  => $result['total_discounts'],
            'total_accruals'   => $result['total_accruals'],
            'total_fee'        => $result['total_fee'],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Internals
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Calcula com uma coleção de taxas explicitamente fornecida (sem consulta ao BD).
     *
     * Usado pelo CustomerBillingReceiptService para aplicar customer_project_fees
     * em vez das project_fees padrão.
     *
     * Se $customFees for vazia/null, cai de volta para o cálculo normal (project_fees + admin_fee).
     *
     * As taxas da coleção devem expor: name, type, nature, value, active (e o método calculate()).
     */
    public function calculateWithFees(SalesProject $project, string $gross, ?Collection $customFees = null): array
    {
        if ($customFees === null || $customFees->isEmpty()) {
            // Sem taxas de cliente → fallback para as taxas de associado normais
            return $this->calculate($project, $gross);
        }

        return $this->applyFees($gross, $customFees, $project);
    }

    private function loadFees(SalesProject $project)
    {
        return ProjectFee::where('sales_project_id', $project->id)
            ->where('active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    private function fallbackCalculation(SalesProject $project, string $gross): array
    {
        $pct = (string) ($project->admin_fee_percentage ?? '0');

        if (bccomp($pct, '0', 4) <= 0) {
            // Sem taxas
            return $this->buildResult($gross, [], '0', '0');
        }

        $amount = bcmul($gross, bcdiv($pct, '100', self::SCALE), self::SCALE);

        $fees = [[
            'id'     => null,
            'name'   => 'Taxa Administrativa',
            'type'   => 'percentage',
            'nature' => 'discount',
            'rate'   => $pct,
            'amount' => $amount,
            'label'  => number_format((float) $pct, 2, ',', '.') . '%',
        ]];

        return $this->buildResult($gross, $fees, $amount, '0');
    }

    private function applyFees(string $gross, $projectFees, ?SalesProject $project = null): array
    {
        $fees           = [];
        $totalDiscounts = '0';
        $totalAccruals  = '0';

        // ── Taxa administrativa do projeto como desconto base (sempre primeiro) ──
        if ($project) {
            $pct = (string) ($project->admin_fee_percentage ?? '0');
            if (bccomp($pct, '0', 4) > 0) {
                $amount = bcmul($gross, bcdiv($pct, '100', self::SCALE), self::SCALE);
                $fees[] = [
                    'id'     => null,
                    'name'   => 'Taxa Administrativa',
                    'type'   => 'percentage',
                    'nature' => 'discount',
                    'rate'   => $pct,
                    'amount' => $amount,
                    'label'  => number_format((float) $pct, 2, ',', '.') . '%',
                ];
                $totalDiscounts = bcadd($totalDiscounts, $amount, self::SCALE);
            }
        }

        // ── Taxas adicionais (frete, bônus, etc.) ───────────────────────────
        foreach ($projectFees as $fee) {
            $amount  = $fee->calculate($gross);
            $nature  = $fee->nature ?? 'discount';
            $typeLabel = $fee->type === 'percentage'
                ? number_format((float) $fee->value, 2, ',', '.') . '%'
                : 'R$ ' . number_format((float) $fee->value, 2, ',', '.');

            $fees[] = [
                'id'     => $fee->id,
                'name'   => $fee->name,
                'type'   => $fee->type,
                'nature' => $nature,
                'rate'   => (string) $fee->value,
                'amount' => $amount,
                'label'  => $typeLabel,
            ];

            if ($nature === 'discount') {
                $totalDiscounts = bcadd($totalDiscounts, $amount, self::SCALE);
            } else {
                $totalAccruals = bcadd($totalAccruals, $amount, self::SCALE);
            }
        }

        return $this->buildResult($gross, $fees, $totalDiscounts, $totalAccruals);
    }

    private function buildResult(string $gross, array $fees, string $totalDiscounts, string $totalAccruals): array
    {
        // Líquido = Bruto - Descontos + Acréscimos
        $net      = bcsub(bcadd($gross, $totalAccruals, self::SCALE), $totalDiscounts, self::SCALE);
        $totalFee = bcsub($totalDiscounts, $totalAccruals, self::SCALE); // redução líquida

        // Percentual efetivo para retrocompatibilidade
        $effPct = bccomp($gross, '0', self::SCALE) !== 0
            ? bcmul(bcdiv($totalFee, $gross, self::SCALE), '100', self::SCALE)
            : '0';

        return [
            'gross'                    => $gross,
            'fees'                     => $fees,
            'total_discounts'          => $totalDiscounts,
            'total_accruals'           => $totalAccruals,
            'total_fee'                => $totalFee,
            'net'                      => $net,
            'admin_fee_percentage_eff' => $effPct,
        ];
    }
}
