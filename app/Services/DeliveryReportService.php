<?php

namespace App\Services;

use App\Enums\DeliveryStatus;
use App\Models\Associate;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductionDelivery;
use App\Models\SalesProject;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class DeliveryReportService
{
    public const TYPES = ['associate', 'product', 'customer'];

    public function __construct(private readonly TenantIdentityService $identity) {}

    public function options(SalesProject $project): array
    {
        $associateIds = $this->receptionsQuery($project)
            ->whereNotNull('associate_id')
            ->distinct()
            ->pluck('associate_id');
        $associates = Associate::query()
            ->where('tenant_id', $project->tenant_id)
            ->whereIn('id', $associateIds)
            ->get(['id', 'tenant_id', 'user_id']);
        $this->identity->namesForAssociates($associates);

        $productIds = $this->receptionsQuery($project)
            ->whereNotNull('product_id')
            ->distinct()
            ->pluck('product_id');
        $products = Product::query()
            ->where('tenant_id', $project->tenant_id)
            ->whereIn('id', $productIds)
            ->orderBy('name')
            ->get(['id', 'name', 'unit']);

        $customerIds = ProductionDelivery::query()
            ->where('tenant_id', $project->tenant_id)
            ->where('sales_project_id', $project->id)
            ->whereNotNull('parent_delivery_id')
            ->whereNotIn('status', $this->excludedStatuses())
            ->whereNotNull('customer_id')
            ->distinct()
            ->pluck('customer_id');
        $customers = Customer::query()
            ->where('tenant_id', $project->tenant_id)
            ->whereIn('id', $customerIds)
            ->get(['id', 'tenant_id', 'organization_id', 'name', 'trade_name'])
            ->sortBy(fn ($customer) => mb_strtolower($customer->trade_name ?: $customer->name))
            ->values()
            ->map(fn ($customer) => [
                'id' => (int) $customer->id,
                'name' => $customer->trade_name ?: $customer->name,
            ]);

        return [
            'project' => [
                'id' => (int) $project->id,
                'title' => $project->title,
                'start_date' => $project->start_date?->format('Y-m-d'),
                'end_date' => $project->end_date?->format('Y-m-d'),
            ],
            'member_term' => $project->tenant?->associateTerm() ?? 'Associado',
            'member_term_plural' => $project->tenant?->associateTerm(plural: true) ?? 'Associados',
            'members' => $associates
                ->sortBy(fn ($associate) => mb_strtolower($associate->display_name))
                ->values()
                ->map(fn ($associate) => [
                    'id' => (int) $associate->id,
                    'name' => $associate->display_name,
                ]),
            'products' => $products
                ->sortBy(fn ($product) => mb_strtolower($product->name))
                ->values()
                ->map(fn ($product) => [
                    'id' => (int) $product->id,
                    'name' => $product->name,
                    'unit' => $product->unit ?: 'un',
                ]),
            'customers' => $customers,
        ];
    }

    public function build(SalesProject $project, array $filters): array
    {
        $type = in_array($filters['type'] ?? null, self::TYPES, true)
            ? $filters['type']
            : 'associate';
        $customerIds = $this->integerIds($filters['customer_ids'] ?? []);

        $query = $this->receptionsQuery($project)
            ->with([
                'associate:id,tenant_id,user_id,cpf_cnpj',
                'product:id,name,unit',
                'distributions' => function ($query) use ($project, $customerIds): void {
                    $query->where('tenant_id', $project->tenant_id)
                        ->where('sales_project_id', $project->id)
                        ->whereNotIn('status', $this->excludedStatuses())
                        ->when($customerIds !== [], fn ($query) => $query->whereIn('customer_id', $customerIds))
                        ->with([
                            'customer:id,tenant_id,organization_id,name,trade_name',
                            'customer.organization:id,tenant_id,name,short_name',
                        ])
                        ->orderBy('customer_id')
                        ->orderBy('id');
                },
            ])
            ->withSum([
                'distributions as all_distributed_quantity' => fn ($query) => $query
                    ->where('tenant_id', $project->tenant_id)
                    ->where('sales_project_id', $project->id)
                    ->whereNotIn('status', $this->excludedStatuses()),
            ], 'quantity')
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('delivery_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('delivery_date', '<=', $date))
            ->when($ids = $this->integerIds($filters['associate_ids'] ?? []), fn (Builder $query) => $query->whereIn('associate_id', $ids))
            ->when($ids = $this->integerIds($filters['product_ids'] ?? []), fn (Builder $query) => $query->whereIn('product_id', $ids))
            ->when($customerIds !== [], function (Builder $query) use ($customerIds, $project): void {
                $query->whereHas('distributions', fn (Builder $query) => $query
                    ->where('tenant_id', $project->tenant_id)
                    ->where('sales_project_id', $project->id)
                    ->whereNotIn('status', $this->excludedStatuses())
                    ->whereIn('customer_id', $customerIds));
            })
            ->orderBy('delivery_date')
            ->orderBy('id');

        $receptions = $query->get();
        $this->identity->namesForAssociates($receptions->pluck('associate')->filter());
        $rows = $receptions->map(fn (ProductionDelivery $delivery) => $this->mapReception($delivery));

        return [
            'type' => $type,
            'project' => $project,
            'filters' => $this->filterLabels($project, $filters, $rows),
            'groups' => $this->group($rows, $type),
            'totals' => $this->totals($rows),
            'rows' => $rows,
        ];
    }

    private function receptionsQuery(SalesProject $project): Builder
    {
        return ProductionDelivery::query()
            ->where('tenant_id', $project->tenant_id)
            ->where('sales_project_id', $project->id)
            ->whereNull('parent_delivery_id')
            ->whereNotIn('status', $this->excludedStatuses());
    }

    private function mapReception(ProductionDelivery $delivery): array
    {
        $distributions = $delivery->distributions->map(function (ProductionDelivery $distribution): array {
            $gross = (float) $distribution->gross_value;

            return [
                'id' => (int) $distribution->id,
                'customer_id' => (int) $distribution->customer_id,
                'customer' => $distribution->customer?->trade_name
                    ?: ($distribution->customer?->name ?? 'Cliente não identificado'),
                'organization' => $distribution->customer?->organization?->short_name
                    ?: ($distribution->customer?->organization?->name ?? null),
                'quantity' => (float) $distribution->quantity,
                'unit_price' => (float) $distribution->unit_price,
                'gross' => $gross,
                'fees' => (float) ($distribution->admin_fee_amount ?? 0),
                'net' => (float) ($distribution->net_value ?? ($gross - (float) ($distribution->admin_fee_amount ?? 0))),
                'status' => $distribution->status?->getLabel() ?? '—',
            ];
        })->values();

        return [
            'id' => (int) $delivery->id,
            'date' => $delivery->delivery_date?->format('d/m/Y') ?? '—',
            'date_iso' => $delivery->delivery_date?->format('Y-m-d'),
            'associate_id' => (int) $delivery->associate_id,
            'associate' => $delivery->associate?->display_name ?? 'Membro não identificado',
            'associate_document' => $delivery->associate?->cpf_cnpj,
            'product_id' => (int) $delivery->product_id,
            'product' => $delivery->product?->name ?? 'Produto não identificado',
            'unit' => $delivery->product?->unit ?: 'un',
            'received_quantity' => (float) $delivery->quantity,
            'distributed_quantity' => (float) $distributions->sum('quantity'),
            'remaining_quantity' => max(0, (float) $delivery->quantity - (float) $delivery->all_distributed_quantity),
            'gross' => (float) $distributions->sum('gross'),
            'fees' => (float) $distributions->sum('fees'),
            'net' => (float) $distributions->sum('net'),
            'status' => $delivery->status?->getLabel() ?? '—',
            'notes' => $delivery->notes,
            'distributions' => $distributions,
        ];
    }

    private function group(Collection $rows, string $type): Collection
    {
        if ($type === 'customer') {
            $customerRows = collect();
            foreach ($rows as $row) {
                foreach ($row['distributions'] as $distribution) {
                    $customerRows->push([
                        'key' => $distribution['customer_id'],
                        'title' => $distribution['customer'],
                        'subtitle' => $distribution['organization'],
                        'delivery' => array_merge($row, [
                            'distributions' => collect([$distribution]),
                            'distributed_quantity' => $distribution['quantity'],
                            'gross' => $distribution['gross'],
                            'fees' => $distribution['fees'],
                            'net' => $distribution['net'],
                        ]),
                    ]);
                }
            }

            return $customerRows->groupBy('key')->map(function (Collection $items): array {
                $first = $items->first();

                return [
                    'title' => $first['title'],
                    'subtitle' => $first['subtitle'],
                    'deliveries' => $items->pluck('delivery')->values(),
                ];
            })->sortBy(fn (array $group) => mb_strtolower($group['title']))->values();
        }

        $key = $type === 'product' ? 'product_id' : 'associate_id';
        $title = $type === 'product' ? 'product' : 'associate';
        $subtitle = $type === 'product' ? 'unit' : 'associate_document';

        return $rows->groupBy($key)->map(fn (Collection $items): array => [
            'title' => $items->first()[$title],
            'subtitle' => $items->first()[$subtitle],
            'deliveries' => $items->values(),
        ])->sortBy(fn (array $group) => mb_strtolower($group['title']))->values();
    }

    private function totals(Collection $rows): array
    {
        return [
            'receptions_count' => $rows->count(),
            'distributions_count' => $rows->sum(fn (array $row) => $row['distributions']->count()),
            'received_quantity' => (float) $rows->sum('received_quantity'),
            'distributed_quantity' => (float) $rows->sum('distributed_quantity'),
            'gross' => (float) $rows->sum('gross'),
            'fees' => (float) $rows->sum('fees'),
            'net' => (float) $rows->sum('net'),
        ];
    }

    private function filterLabels(SalesProject $project, array $filters, Collection $rows): array
    {
        return array_filter([
            'Projeto' => $project->title,
            'Período inicial' => isset($filters['date_from']) ? date('d/m/Y', strtotime($filters['date_from'])) : null,
            'Período final' => isset($filters['date_to']) ? date('d/m/Y', strtotime($filters['date_to'])) : null,
            'Membros selecionados' => $this->integerIds($filters['associate_ids'] ?? []) !== []
                ? $rows->pluck('associate')->unique()->implode(', ')
                : null,
            'Produtos selecionados' => $this->integerIds($filters['product_ids'] ?? []) !== []
                ? $rows->pluck('product')->unique()->implode(', ')
                : null,
            'Clientes selecionados' => $this->integerIds($filters['customer_ids'] ?? []) !== []
                ? $rows->flatMap(fn (array $row) => $row['distributions']->pluck('customer'))->unique()->implode(', ')
                : null,
        ]);
    }

    private function excludedStatuses(): array
    {
        return [DeliveryStatus::REJECTED->value, DeliveryStatus::CANCELLED->value];
    }

    private function integerIds(mixed $ids): array
    {
        return collect(is_array($ids) ? $ids : [])
            ->filter(fn ($id) => filter_var($id, FILTER_VALIDATE_INT) !== false && (int) $id > 0)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
