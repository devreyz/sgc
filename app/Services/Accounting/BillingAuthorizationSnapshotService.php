<?php

namespace App\Services\Accounting;

use App\Models\CustomerBillingReceipt;
use App\Models\Document;
use App\Models\Organization;
use App\Models\ProductionDelivery;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class BillingAuthorizationSnapshotService
{
    public const VERSION = 1;

    /**
     * Build the material document sent to the buyer. The caller must supply
     * distributions re-read from the database; browser payloads are never used.
     *
     * @param  Collection<int, ProductionDelivery>  $distributions
     */
    public function build(CustomerBillingReceipt $receipt, Collection $distributions): array
    {
        $receipt->loadMissing(['tenant', 'project', 'organization', 'customer.organization']);
        if ($distributions instanceof EloquentCollection) {
            $distributions->loadMissing(['product:id,tenant_id,name,unit', 'customer:id,tenant_id,organization_id,name,trade_name']);
        }
        $organization = $this->organizationFor($receipt);
        $feeDefinitions = collect(data_get($receipt->fee_snapshot, 'fees', []))
            ->sortBy(fn (array $fee): string => sprintf('%010d:%s', (int) ($fee['id'] ?? 0), (string) ($fee['name'] ?? '')))
            ->values();
        $projectSnapshots = collect(data_get($receipt->fee_snapshot, 'project_snapshots', []));
        $projects = $receipt->includedProjects()->keyBy('id');
        $isMultiProject = $projects->count() > 1;

        $lines = $distributions->sortBy('id')->values()->map(function (ProductionDelivery $distribution) use ($feeDefinitions, $projectSnapshots, $projects, $isMultiProject): array {
            $lineFeeDefinitions = collect(data_get(
                $projectSnapshots->get((string) $distribution->sales_project_id),
                'fees',
                $feeDefinitions->all(),
            ))->values();
            $gross = $this->decimal($distribution->gross_value ?: bcmul((string) $distribution->quantity, (string) $distribution->unit_price, 8));
            $fees = $lineFeeDefinitions->map(function (array $fee) use ($gross): array {
                $rate = $this->decimal($fee['rate'] ?? 0);
                $amount = ($fee['type'] ?? 'percentage') === 'fixed'
                    ? $rate
                    : $this->decimal(bcmul($gross, bcdiv($rate, '100', 8), 8));

                return [
                    'id' => isset($fee['id']) ? (int) $fee['id'] : null,
                    'name' => (string) ($fee['name'] ?? 'Taxa'),
                    'type' => (string) ($fee['type'] ?? 'percentage'),
                    'nature' => (string) ($fee['nature'] ?? 'discount'),
                    'rate' => $rate,
                    'amount' => $amount,
                ];
            })->all();
            $discounts = collect($fees)->where('nature', 'discount')->reduce(
                fn (string $carry, array $fee): string => bcadd($carry, $fee['amount'], 4), '0.0000'
            );
            $accruals = collect($fees)->where('nature', 'accrual')->reduce(
                fn (string $carry, array $fee): string => bcadd($carry, $fee['amount'], 4), '0.0000'
            );

            $line = [
                'distribution_id' => (int) $distribution->id,
                'parent_delivery_id' => (int) $distribution->parent_delivery_id,
                'delivery_date' => $distribution->delivery_date?->format('Y-m-d'),
                'product' => [
                    'id' => (int) $distribution->product_id,
                    'name' => (string) ($distribution->product?->name ?? 'Produto não identificado'),
                    'unit' => (string) ($distribution->product?->unit ?? ''),
                ],
                'customer' => [
                    'id' => (int) $distribution->customer_id,
                    'name' => (string) ($distribution->customer?->trade_name ?: $distribution->customer?->name ?: 'Cliente não identificado'),
                ],
                'quantity' => $this->decimal($distribution->quantity),
                'unit_price' => $this->decimal($distribution->unit_price),
                'gross' => $gross,
                'fees' => $fees,
                'net' => $this->decimal(bcsub(bcadd($gross, $accruals, 4), $discounts, 4)),
            ];
            if ($isMultiProject) {
                $line['project'] = [
                    'id' => (int) $distribution->sales_project_id,
                    'name' => (string) ($projects->get((int) $distribution->sales_project_id)?->title ?? ''),
                ];
            }

            return $line;
        })->all();
        $documents = Document::query()->where('tenant_id', $receipt->tenant_id)
            ->where('documentable_type', CustomerBillingReceipt::class)
            ->where('documentable_id', $receipt->id)
            ->orderBy('id')->get(['id', 'name', 'original_name', 'category', 'mime_type', 'size', 'document_date'])
            ->map(fn (Document $document): array => [
                'id' => (int) $document->id,
                'name' => (string) ($document->name ?: $document->original_name),
                'category' => $document->category instanceof \BackedEnum ? $document->category->value : (string) $document->category,
                'mime_type' => (string) $document->mime_type,
                'size' => (int) $document->size,
                'date' => $document->document_date?->format('Y-m-d'),
            ])->all();
        $calculatedFees = collect($lines)->flatMap(fn (array $line): array => $line['fees'])
            ->groupBy(fn (array $fee): string => implode(':', [
                $fee['id'] ?? 'custom', $fee['name'], $fee['type'], $fee['nature'], $fee['rate'],
            ]))
            ->map(function (Collection $fees): array {
                $first = $fees->first();

                return [
                    'id' => $first['id'],
                    'name' => $first['name'],
                    'type' => $first['type'],
                    'nature' => $first['nature'],
                    'rate' => $first['rate'],
                    'amount' => $fees->reduce(
                        fn (string $carry, array $fee): string => bcadd($carry, $fee['amount'], 4),
                        '0.0000',
                    ),
                ];
            })->sortBy(fn (array $fee): string => sprintf('%010d:%s', (int) ($fee['id'] ?? 0), $fee['name']))
            ->values()->all();
        $frozenFees = $this->normalize($receipt->fee_snapshot ?? []);
        $frozenFees['calculated'] = $calculatedFees;
        $identity = [
            'tenant' => ['id' => (int) $receipt->tenant_id, 'name' => (string) $receipt->tenant?->name],
            'project' => [
                'id' => (int) $receipt->sales_project_id,
                'name' => (string) $receipt->project?->title,
                'code' => (string) ($receipt->project?->code ?? ''),
            ],
            'receipt' => [
                'id' => (int) $receipt->id,
                'number' => $receipt->formatted_number,
                'issued_at' => $receipt->issued_at?->format('Y-m-d'),
            ],
            'period' => [
                'from' => $receipt->from_date?->format('Y-m-d'),
                'to' => $receipt->to_date?->format('Y-m-d'),
            ],
        ];
        if ($isMultiProject) {
            $identity['projects'] = $projects->values()->map(fn ($project): array => [
                'id' => (int) $project->id,
                'name' => (string) $project->title,
                'code' => (string) ($project->code ?? ''),
                'type' => (string) $project->type,
            ])->all();
        }

        return [
            'snapshot_version' => self::VERSION,
            'identity' => $identity,
            'recipient' => [
                'organization_id' => (int) $organization->id,
                'name' => (string) $organization->name,
                'document' => (string) ($organization->cnpj ?? ''),
                'responsible' => (string) ($organization->responsible_name ?? ''),
                'address' => (string) ($organization->address ?? ''),
                'city' => (string) ($organization->city ?? ''),
                'state' => (string) ($organization->state ?? ''),
                'customer' => $receipt->customer ? [
                    'id' => (int) $receipt->customer->id,
                    'name' => (string) ($receipt->customer->trade_name ?: $receipt->customer->name),
                    'document' => (string) ($receipt->customer->cnpj ?? ''),
                ] : null,
            ],
            'lines' => $lines,
            'fees' => $frozenFees,
            'totals' => [
                'gross' => $this->decimal($receipt->total_gross),
                'fees' => $this->decimal($receipt->total_fees),
                'net' => $this->decimal($receipt->total_net),
            ],
            'document' => [
                'notes' => trim((string) $receipt->notes),
                'line_count' => count($lines),
                'attachments' => $documents,
            ],
        ];
    }

    public function organizationFor(CustomerBillingReceipt $receipt): Organization
    {
        $receipt->loadMissing(['organization', 'customer.organization']);
        $organization = $receipt->organization ?: $receipt->customer?->organization;

        if (! $organization || (int) $organization->tenant_id !== (int) $receipt->tenant_id || ! $organization->active) {
            throw new \RuntimeException('A organização compradora da cobrança não está definida ou ativa.');
        }

        return $organization;
    }

    public function hash(array $snapshot): string
    {
        return hash('sha256', json_encode($this->normalize($snapshot), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    private function normalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(fn (mixed $item): mixed => $this->normalize($item), $value);
        }

        ksort($value, SORT_STRING);

        return array_map(fn (mixed $item): mixed => $this->normalize($item), $value);
    }

    private function decimal(mixed $value): string
    {
        return bcadd((string) ($value ?? 0), '0', 4);
    }
}
