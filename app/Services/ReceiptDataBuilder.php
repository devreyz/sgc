<?php

namespace App\Services;

use Illuminate\Support\Collection;

class ReceiptDataBuilder
{
    /**
     * Build summary and productsSummary arrays from a collection of deliveries.
     *
     * @param  Collection  $deliveries
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

        $productsSummary = $deliveries->map(function ($d) {
            return [
                'product_name'  => $d->product?->name ?? '—',
                'unit'          => $d->product?->unit ?? 'un',
                'delivery_date' => $d->delivery_date,
                'count'         => 1,
                'quantity'      => $d->quantity,
                'unit_price'    => $d->unit_price ?? 0,
                'gross'         => $d->gross_value,
                'admin_fee'     => $d->admin_fee_amount,
                'net'           => $d->net_value,
            ];
        })->values()->all();

        $hasRoundingDivergence = PricingService::hasRoundingDivergence($productsSummary, $summary);

        return [
            'summary'               => $summary,
            'productsSummary'       => $productsSummary,
            'hasRoundingDivergence' => $hasRoundingDivergence,
        ];
    }
}
