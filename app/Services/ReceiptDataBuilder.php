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
     * @param  Collection  $deliveries  — must be distributions (parent_delivery_id NOT NULL)
     * @param  DistributionBilling|null  $billing  — optional billing for fee snapshot
     * @param  SalesProject|null  $project  — optional project for live fee recalculation fallback
     * @return array{summary: array, productsSummary: array, productsByDate: array, productTotals: array, hasRoundingDivergence: bool, feeBreakdown: array}
     */
    public static function fromDeliveries(
        Collection $deliveries,
        ?DistributionBilling $billing = null,
        ?SalesProject $project = null,
        ?array $feeSnapshot = null,
        bool $financialOnly = false,
    ): array {
        $deliveries = $deliveries
            ->filter(fn ($delivery) => ! is_null($delivery->parent_delivery_id))
            ->values();

        $totalGross = (float) $deliveries->sum('gross_value');

        // ── Per-distribution recalculation when project is available ─────────
        // Ensures fees are always fresh from the calculator, not stale DB values.
        $calcMap = [];
        $feeColumns = [];
        $feeColumnService = app(ReceiptFeeColumnService::class);
        $snapshot = $feeSnapshot ?? $billing?->fee_snapshot;

        if ($project) {
            $project->loadMissing('fees');
        }

        if ($project && ! empty($snapshot['fees'])) {
            $feeColumns = $feeColumnService->definitions($project, 'associate', $snapshot);
            foreach ($deliveries as $d) {
                $gross = (float) ($d->gross_value
                    ?? ((float) ($d->quantity ?? 0) * (float) ($d->unit_price ?? 0)));
                $feeValues = $feeColumnService->values($gross, $feeColumns);
                $discounts = 0.0;
                $accruals = 0.0;
                foreach ($feeColumns as $fee) {
                    if ($fee['nature'] === 'accrual') {
                        $accruals += $feeValues[$fee['key']] ?? 0;
                    } else {
                        $discounts += $feeValues[$fee['key']] ?? 0;
                    }
                }
                $calcMap[$d->id] = [
                    'admin_fee' => $discounts - $accruals,
                    'net' => $gross - $discounts + $accruals,
                    'fee_values' => $feeValues,
                ];
            }
        }

        if ($project) {
            /** @var ProjectFinancialCalculator $calculator */
            $calculator = app(ProjectFinancialCalculator::class);
            foreach ($deliveries as $d) {
                if (isset($calcMap[$d->id])) {
                    continue;
                }
                $gross = (string) ($d->gross_value
                    ?? bcmul((string) ($d->quantity ?? 0), (string) ($d->unit_price ?? 0), 8));
                if (bccomp($gross, '0', 4) > 0) {
                    $result = $calculator->calculate($project, $gross);
                    if (empty($feeColumns) && ! empty($result['fees'])) {
                        $feeColumns = $feeColumnService->definitions($project, 'associate', [
                            'fees' => $result['fees'],
                        ]);
                    }
                    $calcMap[$d->id] = [
                        'admin_fee' => (float) $result['total_fee'],
                        'net' => (float) $result['net'],
                        'fee_values' => $feeColumnService->values((float) $gross, $feeColumns),
                    ];
                } else {
                    $calcMap[$d->id] = ['admin_fee' => 0.0, 'net' => 0.0, 'fee_values' => []];
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
            'total_quantity' => $deliveries->sum('quantity'),
            'gross_value' => $totalGross,
            'admin_fee' => $totalFee,
            'net_value' => $totalNet,
            'fee_totals' => self::sumFeeValues($deliveries, $calcMap, $feeColumns),
            'customer_ids' => $deliveries->pluck('customer_id')->filter()->map(fn ($id) => (int) $id)->unique()->values()->all(),
        ];
        $distributionFinancials = $deliveries->mapWithKeys(function ($delivery) use ($calcMap) {
            $gross = (float) ($delivery->gross_value ?? 0);

            return [$delivery->id => [
                'gross' => $gross,
                'fees' => isset($calcMap[$delivery->id])
                    ? (float) $calcMap[$delivery->id]['admin_fee']
                    : (float) ($delivery->admin_fee_amount ?? 0),
                'net' => isset($calcMap[$delivery->id])
                    ? (float) $calcMap[$delivery->id]['net']
                    : ($delivery->net_value !== null
                        ? (float) $delivery->net_value
                        : max(0.0, $gross - (float) ($delivery->admin_fee_amount ?? 0))),
            ]];
        })->all();

        $feeBreakdown = self::buildFeeBreakdown(
            $billing,
            $project,
            $totalGross,
            $totalFee,
            $totalNet,
            $snapshot,
            $feeColumns,
            $summary['fee_totals'],
        );

        if ($financialOnly) {
            return [
                'summary' => $summary,
                'distributionFinancials' => $distributionFinancials,
                'feeBreakdown' => $feeBreakdown,
            ];
        }

        // Flat rows mantidos apenas para verificação de arredondamento
        $flatForCheck = $deliveries->map(fn ($d) => [
            'gross' => (float) ($d->gross_value ?? 0),
            'admin_fee' => isset($calcMap[$d->id]) ? $calcMap[$d->id]['admin_fee'] : (float) ($d->admin_fee_amount ?? 0),
            'net' => isset($calcMap[$d->id]) ? $calcMap[$d->id]['net'] : (float) ($d->net_value ?? 0),
        ])->values()->all();

        $hasRoundingDivergence = PricingService::hasRoundingDivergence($flatForCheck, $summary);

        // Agrupar distribuições pela entrega-pai (mesma recepção = mesmo produto/data)
        $productsSummary = $deliveries
            ->groupBy(fn ($d) => $d->parent_delivery_id ?? ('_'.$d->id))
            ->map(function ($group) use ($calcMap, $feeColumns) {
                $first = $group->first();

                return [
                    'product_id' => (int) ($first->product_id ?? 0),
                    'product_name' => $first->product?->name ?? '—',
                    'unit' => $first->product?->unit ?? 'un',
                    'delivery_date' => $first->delivery_date,
                    'total_quantity' => (float) $group->sum('quantity'),
                    'total_gross' => (float) $group->sum('gross_value'),
                    'total_admin_fee' => ! empty($calcMap)
                        ? (float) array_sum(array_map(fn ($d) => $calcMap[$d->id]['admin_fee'] ?? 0, $group->all()))
                        : (float) $group->sum('admin_fee_amount'),
                    'total_net' => ! empty($calcMap)
                        ? (float) array_sum(array_map(fn ($d) => $calcMap[$d->id]['net'] ?? 0, $group->all()))
                        : (float) $group->sum('net_value'),
                    'fee_totals' => self::sumFeeValues($group, $calcMap, $feeColumns),
                    'distribution_ids' => $group->pluck('id')->map(fn ($id): int => (int) $id)->all(),
                    'distributions' => $group->map(fn ($d) => [
                        'distribution_ids' => [(int) $d->id],
                        'customer_name' => $d->customer?->trade_name ?? $d->customer?->name ?? '—',
                        'quantity' => (float) $d->quantity,
                        'unit_price' => (float) ($d->unit_price ?? 0),
                        'gross' => (float) ($d->gross_value ?? 0),
                        'admin_fee' => isset($calcMap[$d->id]) ? $calcMap[$d->id]['admin_fee'] : (float) ($d->admin_fee_amount ?? 0),
                        'net' => isset($calcMap[$d->id]) ? $calcMap[$d->id]['net'] : (float) ($d->net_value ?? 0),
                        'fee_values' => $calcMap[$d->id]['fee_values'] ?? [],
                    ])->values()->all(),
                ];
            })
            ->values()->all();
        $productsByDate = self::groupProductsByDate($productsSummary);
        $productTotals = self::productTotals($productsSummary);

        return [
            'summary' => $summary,
            'productsSummary' => $productsSummary,
            'productsByDate' => $productsByDate,
            'productTotals' => $productTotals,
            'hasRoundingDivergence' => $hasRoundingDivergence,
            'feeBreakdown' => $feeBreakdown,
            'feeColumns' => $feeColumns,
            'feeColumnOptions' => $feeColumnService->options($feeColumns),
            'distributionFinancials' => $distributionFinancials,
        ];
    }

    /**
     * Consolida recepcoes distintas do mesmo produto e dia. Dentro do grupo,
     * mantem destinos e precos diferentes em linhas separadas para preservar
     * a rastreabilidade financeira do comprovante.
     *
     * @param  array<int, array<string, mixed>>  $products
     * @return array<int, array<string, mixed>>
     */
    private static function groupProductsByDate(array $products): array
    {
        return collect($products)
            ->groupBy(function (array $product): string {
                $date = $product['delivery_date'] ?? null;
                $dateKey = $date instanceof \DateTimeInterface
                    ? $date->format('Y-m-d')
                    : substr(trim((string) $date), 0, 10);

                return implode('|', [
                    ($product['product_id'] ?? 0) > 0
                        ? 'id:'.(int) $product['product_id']
                        : 'name:'.mb_strtolower((string) ($product['product_name'] ?? '')),
                    mb_strtolower((string) ($product['unit'] ?? 'un')),
                    $dateKey,
                ]);
            })
            ->map(function (Collection $groups): array {
                $first = $groups->first();
                $distributions = $groups
                    ->flatMap(fn (array $group): array => $group['distributions'] ?? [])
                    ->groupBy(fn (array $distribution): string => implode('|', [
                        mb_strtolower((string) ($distribution['customer_name'] ?? '')),
                        number_format((float) ($distribution['unit_price'] ?? 0), 8, '.', ''),
                    ]))
                    ->map(function (Collection $rows): array {
                        $row = $rows->first();

                        return [
                            'distribution_ids' => $rows->flatMap(fn (array $item): array => $item['distribution_ids'] ?? [])->unique()->values()->all(),
                            'customer_name' => (string) ($row['customer_name'] ?? '—'),
                            'quantity' => $rows->sum(fn (array $item): float => (float) ($item['quantity'] ?? 0)),
                            'unit_price' => (float) ($row['unit_price'] ?? 0),
                            'gross' => $rows->sum(fn (array $item): float => (float) ($item['gross'] ?? 0)),
                            'admin_fee' => $rows->sum(fn (array $item): float => (float) ($item['admin_fee'] ?? 0)),
                            'net' => $rows->sum(fn (array $item): float => (float) ($item['net'] ?? 0)),
                            'fee_values' => self::sumArrayValues($rows->pluck('fee_values')->all()),
                        ];
                    })
                    ->values()
                    ->all();

                return [
                    'product_id' => (int) ($first['product_id'] ?? 0),
                    'product_name' => (string) ($first['product_name'] ?? '—'),
                    'unit' => (string) ($first['unit'] ?? 'un'),
                    'delivery_date' => $first['delivery_date'] ?? null,
                    'total_quantity' => $groups->sum(fn (array $group): float => (float) ($group['total_quantity'] ?? 0)),
                    'total_gross' => $groups->sum(fn (array $group): float => (float) ($group['total_gross'] ?? 0)),
                    'total_admin_fee' => $groups->sum(fn (array $group): float => (float) ($group['total_admin_fee'] ?? 0)),
                    'total_net' => $groups->sum(fn (array $group): float => (float) ($group['total_net'] ?? 0)),
                    'fee_totals' => self::sumArrayValues($groups->pluck('fee_totals')->all()),
                    'distribution_ids' => $groups->flatMap(fn (array $group): array => $group['distribution_ids'] ?? [])->unique()->values()->all(),
                    'distributions' => $distributions,
                ];
            })
            ->sortBy(function (array $product): string {
                $date = $product['delivery_date'] ?? null;
                $dateKey = $date instanceof \DateTimeInterface
                    ? $date->format('Y-m-d')
                    : substr(trim((string) $date), 0, 10);

                return $dateKey.'|'.mb_strtolower($product['product_name']);
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $products
     * @return array<int, array<string, mixed>>
     */
    private static function productTotals(array $products): array
    {
        return collect($products)
            ->groupBy(fn (array $product): string => implode('|', [
                ($product['product_id'] ?? 0) > 0
                    ? 'id:'.(int) $product['product_id']
                    : 'name:'.mb_strtolower((string) ($product['product_name'] ?? '')),
                mb_strtolower((string) ($product['unit'] ?? 'un')),
            ]))
            ->map(function (Collection $groups): array {
                $first = $groups->first();

                return [
                    'product_id' => (int) ($first['product_id'] ?? 0),
                    'product_name' => (string) ($first['product_name'] ?? '—'),
                    'unit' => (string) ($first['unit'] ?? 'un'),
                    'total_quantity' => $groups->sum(fn (array $group): float => (float) ($group['total_quantity'] ?? 0)),
                    'total_gross' => $groups->sum(fn (array $group): float => (float) ($group['total_gross'] ?? 0)),
                    'total_admin_fee' => $groups->sum(fn (array $group): float => (float) ($group['total_admin_fee'] ?? 0)),
                    'total_net' => $groups->sum(fn (array $group): float => (float) ($group['total_net'] ?? 0)),
                    'fee_totals' => self::sumArrayValues($groups->pluck('fee_totals')->all()),
                ];
            })
            ->sortBy(fn (array $product): string => mb_strtolower($product['product_name']))
            ->values()
            ->all();
    }

    /** @param array<int, mixed> $arrays @return array<string, float> */
    private static function sumArrayValues(array $arrays): array
    {
        $totals = [];
        foreach ($arrays as $values) {
            foreach ((array) $values as $key => $value) {
                $totals[$key] = ($totals[$key] ?? 0.0) + (float) $value;
            }
        }

        return $totals;
    }

    private static function sumFeeValues(Collection $deliveries, array $calcMap, array $feeColumns): array
    {
        $totals = array_fill_keys(array_column($feeColumns, 'key'), 0.0);

        foreach ($deliveries as $delivery) {
            foreach (($calcMap[$delivery->id]['fee_values'] ?? []) as $key => $amount) {
                $totals[$key] = ($totals[$key] ?? 0) + (float) $amount;
            }
        }

        return $totals;
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
        float|string $totalNet,
        ?array $explicitSnapshot = null,
        array $feeColumns = [],
        array $feeTotals = [],
    ): array {
        $snapshot = $explicitSnapshot ?? $billing?->fee_snapshot;

        // ── Prioridade 1: snapshot congelado no faturamento ──────────────────
        if ($snapshot && ! empty($snapshot['fees'])) {
            $fees = array_map(fn ($f, $index) => [
                'name' => $f['name'],
                'nature' => $f['nature'] ?? 'discount',
                'amount' => (float) ($feeTotals[$feeColumns[$index]['key'] ?? ''] ?? $f['amount'] ?? 0),
                'label' => $f['label'] ?? '',
            ], $snapshot['fees'], array_keys($snapshot['fees']));

            return [
                'fees' => $fees,
                'total_discounts' => (float) ($snapshot['total_discounts'] ?? $totalFee),
                'total_accruals' => (float) ($snapshot['total_accruals'] ?? 0),
                'has_detail' => true,
            ];
        }

        // ── Prioridade 2: cálculo ao vivo quando projeto disponível ──────────
        $grossStr = (string) $totalGross;
        if ($project && bccomp($grossStr, '0', 4) > 0) {
            /** @var ProjectFinancialCalculator $calculator */
            $calculator = app(ProjectFinancialCalculator::class);
            $result = $calculator->calculate($project, $grossStr);

            if (! empty($result['fees'])) {
                $fees = array_map(fn ($f, $index) => [
                    'name' => $f['name'],
                    'nature' => $f['nature'] ?? 'discount',
                    'amount' => (float) ($feeTotals[$feeColumns[$index]['key'] ?? ''] ?? $f['amount']),
                    'label' => $f['label'] ?? '',
                ], $result['fees'], array_keys($result['fees']));

                $discountTotal = 0.0;
                $accrualTotal = 0.0;
                foreach ($feeColumns as $fee) {
                    if ($fee['nature'] === 'accrual') {
                        $accrualTotal += (float) ($feeTotals[$fee['key']] ?? 0);
                    } else {
                        $discountTotal += (float) ($feeTotals[$fee['key']] ?? 0);
                    }
                }

                return [
                    'fees' => $fees,
                    'total_discounts' => $discountTotal,
                    'total_accruals' => $accrualTotal,
                    'has_detail' => true,
                ];
            }
        }

        // ── Prioridade 3: fallback — total armazenado sem detalhamento ───────
        $feeTotal = (float) $totalFee;
        if ($feeTotal == 0) {
            return ['fees' => [], 'total_discounts' => 0.0, 'total_accruals' => 0.0, 'has_detail' => false];
        }

        return [
            'fees' => [['name' => 'Taxa Administrativa', 'nature' => 'discount', 'amount' => $feeTotal, 'label' => '']],
            'total_discounts' => $feeTotal,
            'total_accruals' => 0.0,
            'has_detail' => false,
        ];
    }
}
