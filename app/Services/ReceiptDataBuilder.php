<?php

namespace App\Services;

use Illuminate\Support\Collection;

class ReceiptDataBuilder
{
    /**
     * Build summary and productsSummary arrays from a collection of distributions.
     * Each distribution represents a financial sale (customer, qty, price, net_value).
     *
     * @param  Collection  $deliveries  — must be distributions (parent_delivery_id NOT NULL)
     * @return array{summary: array, productsSummary: array, hasRoundingDivergence: bool}
     */
    public static function fromDeliveries(Collection $deliveries): array
    {
        $summary = [
            'deliveries_count' => $deliveries->count(),
            'total_quantity'   => $deliveries->sum('quantity'),
            'gross_value'      => $deliveries->sum('gross_value'),
            'admin_fee'        => $deliveries->sum('admin_fee_amount'),
            'net_value'        => $deliveries->sum('net_value'),
        ];

        // Flat rows mantidos apenas para verificação de arredondamento
        $flatForCheck = $deliveries->map(fn($d) => [
            'gross'     => (float) ($d->gross_value ?? 0),
            'admin_fee' => (float) ($d->admin_fee_amount ?? 0),
            'net'       => (float) ($d->net_value ?? 0),
        ])->values()->all();

        $hasRoundingDivergence = PricingService::hasRoundingDivergence($flatForCheck, $summary);

        // Agrupar distribuições pela entrega-pai (mesma recepção = mesmo produto/data)
        $productsSummary = $deliveries
            ->groupBy(fn($d) => $d->parent_delivery_id ?? ('_' . $d->id))
            ->map(function ($group) {
                $first = $group->first();
                return [
                    'product_name'    => $first->product?->name ?? '—',
                    'unit'            => $first->product?->unit ?? 'un',
                    'delivery_date'   => $first->delivery_date,
                    'total_quantity'  => (float) $group->sum('quantity'),
                    'total_gross'     => (float) $group->sum('gross_value'),
                    'total_admin_fee' => (float) $group->sum('admin_fee_amount'),
                    'total_net'       => (float) $group->sum('net_value'),
                    'distributions'   => $group->map(fn($d) => [
                        'customer_name' => $d->customer?->trade_name ?? $d->customer?->name ?? '—',
                        'quantity'      => (float) $d->quantity,
                        'unit_price'    => (float) ($d->unit_price ?? 0),
                        'gross'         => (float) ($d->gross_value ?? 0),
                        'admin_fee'     => (float) ($d->admin_fee_amount ?? 0),
                        'net'           => (float) ($d->net_value ?? 0),
                    ])->values()->all(),
                ];
            })
            ->values()->all();

        return [
            'summary'               => $summary,
            'productsSummary'       => $productsSummary,
            'hasRoundingDivergence' => $hasRoundingDivergence,
        ];
    }
}
