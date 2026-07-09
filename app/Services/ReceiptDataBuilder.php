<?php

namespace App\Services;

use App\Models\DistributionBilling;
use App\Models\SalesProject;
use Illuminate\Support\Collection;

class ReceiptDataBuilder
{
    /**
     * Build summary and productsSummary arrays from a collection of distributions.
     * Each distribution represents a financial sale (customer, qty, price, net_value).
     *
     * Optionally pass a DistributionBilling to include the fee snapshot (frozen at billing time).
     * Optionally pass a SalesProject to compute a live fee breakdown when no snapshot is available.
     *
     * @param  Collection               $deliveries  — must be distributions (parent_delivery_id NOT NULL)
     * @param  DistributionBilling|null $billing     — optional billing for fee snapshot
     * @param  SalesProject|null        $project     — optional project for live fee recalculation fallback
     * @return array{summary: array, productsSummary: array, hasRoundingDivergence: bool, feeBreakdown: array}
     */
    public static function fromDeliveries(
        Collection $deliveries,
        ?DistributionBilling $billing = null,
        ?SalesProject $project = null
    ): array {
        $deliveries = $deliveries
            ->filter(fn ($delivery) => ! is_null($delivery->parent_delivery_id))
            ->values();

        $totalGross = (float) $deliveries->sum('gross_value');

        // ── Per-distribution recalculation when project is available ─────────
        // Ensures fees are always fresh from the calculator, not stale DB values.
        $calcMap = [];
        if ($project) {
            /** @var ProjectFinancialCalculator $calculator */
            $calculator = app(ProjectFinancialCalculator::class);
            foreach ($deliveries as $d) {
                $gross = (string) ($d->gross_value
                    ?? bcmul((string) ($d->quantity ?? 0), (string) ($d->unit_price ?? 0), 8));
                if (bccomp($gross, '0', 4) > 0) {
                    $result = $calculator->calculate($project, $gross);
                    $calcMap[$d->id] = [
                        'admin_fee' => (float) $result['total_fee'],
                        'net'       => (float) $result['net'],
                    ];
                } else {
                    $calcMap[$d->id] = ['admin_fee' => 0.0, 'net' => 0.0];
                }
            }
        }

        // Use recalculated values when available; fall back to DB values
        $totalFee = ! empty($calcMap)
            ? (float) array_sum(array_column($calcMap, 'admin_fee'))
            : (float) $deliveries->sum('admin_fee_amount');

        $totalNet = ! empty($calcMap)
            ? (float) array_sum(array_column($calcMap, 'net'))
            : (float) $deliveries->sum('net_value');

        $summary = [
            'deliveries_count' => $deliveries->count(),
            'total_quantity'   => $deliveries->sum('quantity'),
            'gross_value'      => $totalGross,
            'admin_fee'        => $totalFee,
            'net_value'        => $totalNet,
        ];

        // Flat rows mantidos apenas para verificação de arredondamento
        $flatForCheck = $deliveries->map(fn($d) => [
            'gross'     => (float) ($d->gross_value ?? 0),
            'admin_fee' => isset($calcMap[$d->id]) ? $calcMap[$d->id]['admin_fee'] : (float) ($d->admin_fee_amount ?? 0),
            'net'       => isset($calcMap[$d->id]) ? $calcMap[$d->id]['net'] : (float) ($d->net_value ?? 0),
        ])->values()->all();

        $hasRoundingDivergence = PricingService::hasRoundingDivergence($flatForCheck, $summary);

        // Breakdown detalhado das taxas
        $feeBreakdown = self::buildFeeBreakdown($billing, $project, $totalGross, $totalFee, $totalNet);

        // Agrupar distribuições pela entrega-pai (mesma recepção = mesmo produto/data)
        $productsSummary = $deliveries
            ->groupBy(fn($d) => $d->parent_delivery_id ?? ('_' . $d->id))
            ->map(function ($group) use ($calcMap) {
                $first = $group->first();
                return [
                    'product_name'    => $first->product?->name ?? '—',
                    'unit'            => $first->product?->unit ?? 'un',
                    'delivery_date'   => $first->delivery_date,
                    'total_quantity'  => (float) $group->sum('quantity'),
                    'total_gross'     => (float) $group->sum('gross_value'),
                    'total_admin_fee' => ! empty($calcMap)
                        ? (float) array_sum(array_map(fn($d) => $calcMap[$d->id]['admin_fee'] ?? 0, $group->all()))
                        : (float) $group->sum('admin_fee_amount'),
                    'total_net'       => ! empty($calcMap)
                        ? (float) array_sum(array_map(fn($d) => $calcMap[$d->id]['net'] ?? 0, $group->all()))
                        : (float) $group->sum('net_value'),
                    'distributions'   => $group->map(fn($d) => [
                        'customer_name' => $d->customer?->trade_name ?? $d->customer?->name ?? '—',
                        'quantity'      => (float) $d->quantity,
                        'unit_price'    => (float) ($d->unit_price ?? 0),
                        'gross'         => (float) ($d->gross_value ?? 0),
                        'admin_fee'     => isset($calcMap[$d->id]) ? $calcMap[$d->id]['admin_fee'] : (float) ($d->admin_fee_amount ?? 0),
                        'net'           => isset($calcMap[$d->id]) ? $calcMap[$d->id]['net'] : (float) ($d->net_value ?? 0),
                    ])->values()->all(),
                ];
            })
            ->values()->all();

        return [
            'summary'               => $summary,
            'productsSummary'       => $productsSummary,
            'hasRoundingDivergence' => $hasRoundingDivergence,
            'feeBreakdown'          => $feeBreakdown,
        ];
    }

    /**
     * Monta o breakdown de taxas para exibição em comprovantes.
     *
     * Prioridade:
     *   1. Snapshot congelado do faturamento (DistributionBilling.fee_snapshot)
     *   2. Cálculo ao vivo via ProjectFinancialCalculator (quando $project é fornecido)
     *   3. Fallback genérico: exibe o total armazenado como "Taxa Administrativa"
     *
     * @return array{
     *   fees: list<array{name: string, nature: string, amount: float, label: string}>,
     *   total_discounts: float,
     *   total_accruals: float,
     *   has_detail: bool,
     * }
     */
    private static function buildFeeBreakdown(
        ?DistributionBilling $billing,
        ?SalesProject $project,
        float|string $totalGross,
        float|string $totalFee,
        float|string $totalNet
    ): array {
        $snapshot = $billing?->fee_snapshot;

        // ── Prioridade 1: snapshot congelado no faturamento ──────────────────
        if ($snapshot && ! empty($snapshot['fees'])) {
            $fees = array_map(fn($f) => [
                'name'   => $f['name'],
                'nature' => $f['nature'] ?? 'discount',
                'amount' => (float) ($f['amount'] ?? 0),
                'label'  => $f['label'] ?? '',
            ], $snapshot['fees']);

            return [
                'fees'             => $fees,
                'total_discounts'  => (float) ($snapshot['total_discounts'] ?? $totalFee),
                'total_accruals'   => (float) ($snapshot['total_accruals'] ?? 0),
                'has_detail'       => true,
            ];
        }

        // ── Prioridade 2: cálculo ao vivo quando projeto disponível ──────────
        $grossStr = (string) $totalGross;
        if ($project && bccomp($grossStr, '0', 4) > 0) {
            /** @var ProjectFinancialCalculator $calculator */
            $calculator = app(ProjectFinancialCalculator::class);
            $result = $calculator->calculate($project, $grossStr);

            if (! empty($result['fees'])) {
                $fees = array_map(fn($f) => [
                    'name'   => $f['name'],
                    'nature' => $f['nature'] ?? 'discount',
                    'amount' => (float) $f['amount'],
                    'label'  => $f['label'] ?? '',
                ], $result['fees']);

                return [
                    'fees'            => $fees,
                    'total_discounts' => (float) $result['total_discounts'],
                    'total_accruals'  => (float) $result['total_accruals'],
                    'has_detail'      => true,
                ];
            }
        }

        // ── Prioridade 3: fallback — total armazenado sem detalhamento ───────
        $feeTotal = (float) $totalFee;
        if ($feeTotal == 0) {
            return ['fees' => [], 'total_discounts' => 0.0, 'total_accruals' => 0.0, 'has_detail' => false];
        }

        return [
            'fees'            => [['name' => 'Taxa Administrativa', 'nature' => 'discount', 'amount' => $feeTotal, 'label' => '']],
            'total_discounts' => $feeTotal,
            'total_accruals'  => 0.0,
            'has_detail'      => false,
        ];
    }
}
