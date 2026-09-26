<?php

namespace App\Services;

use App\Models\CustomerProjectFee;
use App\Models\ProductionDelivery;
use App\Models\SalesProject;
use App\Support\FinancialAmount;
use Illuminate\Support\Collection;

/**
 * Consolida distribuicoes em linhas documentais deterministicas.
 *
 * Quantidade e preco permanecem decimais (BCMath). O arredondamento HALF_UP
 * acontece uma unica vez por linha consolidada, quando o valor passa a fazer
 * parte do documento. Nenhum total financeiro e calculado com float.
 */
final class CustomerBillingLineConsolidator
{
    private const SCALE = 8;

    public function __construct(private readonly ProjectFinancialCalculator $calculator) {}

    /**
     * @param  Collection<int, ProductionDelivery>  $distributions
     * @return array{lines: list<array<string, mixed>>, fees: list<array<string, mixed>>, total_gross: string, total_fees: string, total_net: string, total_discounts: string, total_accruals: string}
     */
    public function consolidate(Collection $distributions, SalesProject $project): array
    {
        $customerFees = CustomerProjectFee::query()
            ->where('tenant_id', $project->tenant_id)
            ->where('sales_project_id', $project->id)
            ->where('active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $groups = [];
        foreach ($distributions as $distribution) {
            $unit = $this->unit($distribution);
            $price = $this->decimal($distribution->unit_price);
            $ncm = $distribution->relationLoaded('product') ? trim((string) ($distribution->product?->ncm ?? '')) : '';
            $key = implode('|', [
                (int) $distribution->sales_project_id,
                (int) $distribution->product_id,
                mb_strtolower($unit),
                $price,
                $ncm,
            ]);

            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'project_id' => (int) $distribution->sales_project_id,
                    'project' => (string) ($project->title ?? ''),
                    'product_id' => (int) $distribution->product_id,
                    'product' => $this->productName($distribution),
                    'unit' => $unit,
                    'ncm' => $ncm !== '' ? $ncm : null,
                    'unit_price' => $price,
                    'quantity' => '0.00000000',
                    'distribution_ids' => [],
                ];
            }

            $groups[$key]['quantity'] = bcadd(
                $groups[$key]['quantity'],
                $this->decimal($distribution->quantity),
                self::SCALE,
            );
            $groups[$key]['distribution_ids'][] = (int) $distribution->id;
        }

        $lines = [];
        $feeTotals = [];
        $totalGross = '0.00';
        $totalDiscounts = '0.00';
        $totalAccruals = '0.00';
        $totalNet = '0.00';

        foreach ($groups as $group) {
            $rawGross = bcmul($group['quantity'], $group['unit_price'], self::SCALE);
            $documentGross = FinancialAmount::cents($rawGross);
            $calculation = $this->calculator->calculateWithFees($project, $rawGross, $customerFees);
            $lineDiscounts = '0.00';
            $lineAccruals = '0.00';
            $lineFees = [];

            foreach ($calculation['fees'] as $fee) {
                $amount = FinancialAmount::cents($fee['amount'] ?? '0');
                $fee['raw_amount'] = (string) ($fee['amount'] ?? '0');
                $fee['amount'] = $amount;
                $lineFees[] = $fee;

                $feeKey = implode('|', [
                    $fee['id'] ?? 'custom', $fee['name'] ?? '', $fee['type'] ?? '',
                    $fee['nature'] ?? '', $fee['rate'] ?? '',
                ]);
                if (! isset($feeTotals[$feeKey])) {
                    $feeTotals[$feeKey] = array_merge($fee, ['amount' => '0.00']);
                }
                $feeTotals[$feeKey]['amount'] = bcadd($feeTotals[$feeKey]['amount'], $amount, 2);

                if (($fee['nature'] ?? 'discount') === 'accrual') {
                    $lineAccruals = bcadd($lineAccruals, $amount, 2);
                } else {
                    $lineDiscounts = bcadd($lineDiscounts, $amount, 2);
                }
            }

            $lineFee = bcsub($lineDiscounts, $lineAccruals, 2);
            $documentAmount = bcsub(bcadd($documentGross, $lineAccruals, 2), $lineDiscounts, 2);
            $lines[] = array_merge($group, [
                'quantity' => $this->trimDecimal($group['quantity']),
                'raw_amount' => $rawGross,
                'document_gross' => $documentGross,
                'document_fees' => $lineFee,
                'document_amount' => $documentAmount,
                'fee_values' => collect($lineFees)->mapWithKeys(
                    fn (array $fee): array => [ReceiptFeeColumnService::PREFIX.'customer:'.($fee['id'] ?? 'admin') => $fee['amount']]
                )->all(),
                'fees' => $lineFees,
            ]);

            $totalGross = bcadd($totalGross, $documentGross, 2);
            $totalDiscounts = bcadd($totalDiscounts, $lineDiscounts, 2);
            $totalAccruals = bcadd($totalAccruals, $lineAccruals, 2);
            $totalNet = bcadd($totalNet, $documentAmount, 2);
        }

        return [
            'lines' => array_values($lines),
            'fees' => array_values($feeTotals),
            'total_gross' => $totalGross,
            'total_discounts' => $totalDiscounts,
            'total_accruals' => $totalAccruals,
            'total_fees' => bcsub($totalDiscounts, $totalAccruals, 2),
            'total_net' => $totalNet,
        ];
    }

    private function unit(ProductionDelivery $distribution): string
    {
        $unit = $distribution->relationLoaded('product') ? $distribution->product?->unit : null;

        return trim((string) ($unit ?: 'un'));
    }

    private function productName(ProductionDelivery $distribution): string
    {
        $name = $distribution->relationLoaded('product') ? $distribution->product?->name : null;

        return trim((string) ($name ?: 'Produto #'.$distribution->product_id));
    }

    private function decimal(mixed $value): string
    {
        $value = trim((string) ($value ?? '0'));

        return preg_match('/^-?\d+(?:\.\d+)?$/', $value) ? $value : '0';
    }

    private function trimDecimal(string $value): string
    {
        $value = rtrim(rtrim($value, '0'), '.');

        return $value === '' || $value === '-0' ? '0' : $value;
    }
}
