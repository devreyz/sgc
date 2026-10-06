<?php

namespace App\Services;

use App\Enums\DeliveryStatus;
use App\Models\AssociateReceipt;
use App\Models\Customer;
use App\Models\FinancialDocumentIdentity;
use App\Models\ProductionDelivery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/** Resolve selecao de faturamento sem usar o total do comprovante do associado. */
final class CustomerBillingSelectionService
{
    /** @var array<string, array<int, string>> */
    private array $lockedMapCache = [];

    /**
     * Distribuicoes reservadas por outro faturamento, inclusive enquanto ele
     * ainda e um rascunho e so possui a selecao salva em delivery_ids.
     *
     * @return array<int, string> [distribution_id => billing label]
     */
    public function lockedDistributionMap(int $tenantId, ?int $currentReceiptId = null): array
    {
        $cacheKey = $tenantId.':'.($currentReceiptId ?? 'new');
        if (array_key_exists($cacheKey, $this->lockedMapCache)) {
            return $this->lockedMapCache[$cacheKey];
        }

        if (! Schema::hasTable('customer_billing_receipts')) {
            return [];
        }

        $receipts = DB::table('customer_billing_receipts')
            ->where('tenant_id', $tenantId)
            ->when($currentReceiptId, fn ($query) => $query->where('id', '!=', $currentReceiptId))
            ->get(['id', 'receipt_label', 'receipt_year', 'receipt_number', 'delivery_ids']);

        $labels = $receipts->mapWithKeys(function ($receipt): array {
            $label = filled($receipt->receipt_label)
                ? (string) $receipt->receipt_label
                : 'COM-'.str_pad((string) $receipt->receipt_number, 4, '0', STR_PAD_LEFT).'/'.$receipt->receipt_year;

            return [(int) $receipt->id => $label];
        });

        $locked = [];
        foreach ($receipts as $receipt) {
            $ids = is_array($receipt->delivery_ids)
                ? $receipt->delivery_ids
                : json_decode((string) ($receipt->delivery_ids ?? '[]'), true);

            foreach ((array) $ids as $id) {
                if ((int) $id > 0) {
                    $locked[(int) $id] = $labels->get((int) $receipt->id, 'outro faturamento');
                }
            }
        }

        // O vinculo na distribuicao e a protecao definitiva. Ele tambem cobre
        // registros antigos que possam nao possuir delivery_ids consistente.
        ProductionDelivery::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNotNull('billing_receipt_id')
            ->when($currentReceiptId, fn ($query) => $query->where('billing_receipt_id', '!=', $currentReceiptId))
            ->get(['id', 'billing_receipt_id'])
            ->each(function (ProductionDelivery $distribution) use (&$locked, $labels): void {
                $locked[(int) $distribution->id] = $labels->get(
                    (int) $distribution->billing_receipt_id,
                    'outro faturamento'
                );
            });

        ksort($locked);

        return $this->lockedMapCache[$cacheKey] = $locked;
    }

    /** @return list<int> */
    public function lockedDistributionIds(int $tenantId, ?int $currentReceiptId = null): array
    {
        return array_keys($this->lockedDistributionMap($tenantId, $currentReceiptId));
    }

    public function eligibleQuery(
        int $tenantId,
        array $projectIds,
        ?int $customerId,
        ?int $organizationId,
        ?string $from,
        ?string $to,
        ?int $currentReceiptId = null,
    ): Builder {
        $lockedIds = $this->lockedDistributionIds($tenantId, $currentReceiptId);
        $query = ProductionDelivery::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->whereIn('sales_project_id', collect($projectIds)->map(fn ($id): int => (int) $id)->filter()->unique())
            ->whereNotNull('parent_delivery_id')
            ->whereExists(function ($parent): void {
                $parent->selectRaw('1')
                    ->from('production_deliveries as billing_parent')
                    ->whereColumn('billing_parent.id', 'production_deliveries.parent_delivery_id')
                    ->whereColumn('billing_parent.tenant_id', 'production_deliveries.tenant_id')
                    ->whereColumn('billing_parent.sales_project_id', 'production_deliveries.sales_project_id')
                    ->whereNull('billing_parent.parent_delivery_id')
                    ->whereNull('billing_parent.deleted_at');
            })
            ->where('status', DeliveryStatus::APPROVED->value)
            ->where('quantity', '>', 0)
            ->where('unit_price', '>', 0)
            ->where(function (Builder $billing) use ($currentReceiptId): void {
                $billing->whereNull('billing_receipt_id');
                if ($currentReceiptId) {
                    $billing->orWhere('billing_receipt_id', $currentReceiptId);
                }
            });

        if ($lockedIds !== []) {
            $query->whereNotIn('id', $lockedIds);
        }

        if ($from) {
            $query->whereDate('delivery_date', '>=', $from);
        }
        if ($to) {
            $query->whereDate('delivery_date', '<=', $to);
        }
        if ($customerId) {
            $query->where('customer_id', $customerId);
        } elseif ($organizationId) {
            $customerIds = Customer::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->whereNull('deleted_at')
                ->where('organization_id', $organizationId)
                ->pluck('id');
            $query->whereIn('customer_id', $customerIds);
        } else {
            $query->whereRaw('1 = 0');
        }

        return $query;
    }

    /**
     * O comprovante e somente um seletor. Seus totais nunca entram no calculo.
     *
     * @return array{selected_ids:list<int>, receipt_count:int, candidate_count:int, excluded_count:int, reasons:array<string,int>}
     */
    public function selectFromAssociateReceiptCodes(
        int $tenantId,
        array $codes,
        array $projectIds,
        ?int $customerId,
        ?int $organizationId,
        ?string $from,
        ?string $to,
        ?int $currentReceiptId = null,
    ): array {
        $receipts = $this->resolveAssociateReceipts($tenantId, $codes);
        $candidateIds = ProductionDelivery::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->whereIn('associate_receipt_id', $receipts->pluck('id'))
            ->pluck('id')->map(fn ($id): int => (int) $id)->unique()->values();

        $selection = $this->selectDistributionIds(
            $tenantId, $candidateIds->all(), $projectIds, $customerId, $organizationId, $from, $to, $currentReceiptId,
        );

        $selectedIds = collect($selection['selected_ids']);
        $distributions = ProductionDelivery::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)->whereIn('id', $selectedIds)
            ->with(['product:id,name,unit'])->orderBy('delivery_date')->orderBy('id')
            ->get(['id', 'associate_receipt_id', 'product_id', 'delivery_date', 'quantity']);

        $documents = $receipts->loadMissing(['project', 'associate.user'])->map(function (AssociateReceipt $receipt) use ($distributions): array {
            $items = $distributions->where('associate_receipt_id', $receipt->id)->values();

            return [
                'id' => (int) $receipt->id,
                'number' => $receipt->formatted_number,
                'associate' => $receipt->associate?->display_name ?? 'Associado não identificado',
                'distributions' => $items->map(fn (ProductionDelivery $row): array => [
                    'id' => (int) $row->id,
                    'date' => $row->delivery_date?->format('d/m/Y'),
                    'product' => $row->product?->name ?? 'Produto',
                    'quantity' => (string) $row->quantity,
                    'unit' => $row->product?->unit ?: 'un',
                ])->all(),
            ];
        })->values()->all();

        return $selection + ['receipt_count' => $receipts->count(), 'documents' => $documents];
    }

    /**
     * Revalida uma selecao manual no servidor e explica todos os IDs recusados.
     *
     * @return array{selected_ids:list<int>, candidate_count:int, excluded_count:int, reasons:array<string,int>}
     */
    public function selectDistributionIds(
        int $tenantId,
        array $distributionIds,
        array $projectIds,
        ?int $customerId,
        ?int $organizationId,
        ?string $from,
        ?string $to,
        ?int $currentReceiptId = null,
    ): array {
        $candidateIds = collect($distributionIds)->map(fn ($id): int => (int) $id)->filter()->unique()->values();
        $selected = $this->eligibleQuery(
            $tenantId, $projectIds, $customerId, $organizationId, $from, $to, $currentReceiptId,
        )->whereIn('id', $candidateIds)->pluck('id')->map(fn ($id): int => (int) $id)->unique()->values();

        $reasons = [];
        $excluded = $candidateIds->diff($selected);
        if ($excluded->isNotEmpty()) {
            $lockedIds = $this->lockedDistributionIds($tenantId, $currentReceiptId);
            $rows = ProductionDelivery::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)->whereIn('id', $excluded)
                ->get(['id', 'sales_project_id', 'customer_id', 'parent_delivery_id', 'delivery_date', 'quantity', 'unit_price', 'status', 'billing_receipt_id', 'deleted_at']);
            $parents = ProductionDelivery::withoutGlobalScopes()->withTrashed()
                ->whereIn('id', $rows->pluck('parent_delivery_id')->filter()->unique())
                ->get(['id', 'tenant_id', 'sales_project_id', 'parent_delivery_id', 'deleted_at'])
                ->keyBy('id');
            $organizationCustomerIds = $organizationId
                ? Customer::withoutGlobalScopes()->where('tenant_id', $tenantId)->whereNull('deleted_at')->where('organization_id', $organizationId)->pluck('id')->map(fn ($id): int => (int) $id)->all()
                : [];
            $foundIds = $rows->pluck('id')->map(fn ($id): int => (int) $id);
            $missingCount = $excluded->diff($foundIds)->count();
            if ($missingCount > 0) {
                $reasons['nao_encontrada_no_tenant'] = $missingCount;
            }
            foreach ($rows as $row) {
                $reason = match (true) {
                    in_array((int) $row->id, $lockedIds, true) => 'ja_faturada',
                    $row->deleted_at !== null => 'removida',
                    ! in_array((int) $row->sales_project_id, array_map('intval', $projectIds), true) => 'outro_projeto',
                    $customerId && (int) $row->customer_id !== $customerId => 'outro_destinatario',
                    $organizationId && ! in_array((int) $row->customer_id, $organizationCustomerIds, true) => 'outro_destinatario',
                    $from && $row->delivery_date?->format('Y-m-d') < $from => 'fora_do_periodo',
                    $to && $row->delivery_date?->format('Y-m-d') > $to => 'fora_do_periodo',
                    ! $row->parent_delivery_id => 'nao_e_distribuicao',
                    ! $parents->has((int) $row->parent_delivery_id) => 'entrega_pai_inexistente',
                    $parents->get((int) $row->parent_delivery_id)?->deleted_at !== null => 'entrega_pai_removida',
                    (int) $parents->get((int) $row->parent_delivery_id)?->tenant_id !== $tenantId
                        || (int) $parents->get((int) $row->parent_delivery_id)?->sales_project_id !== (int) $row->sales_project_id
                        || $parents->get((int) $row->parent_delivery_id)?->parent_delivery_id !== null => 'entrega_pai_incompativel',
                    ($row->status instanceof DeliveryStatus ? $row->status->value : (string) $row->status) !== DeliveryStatus::APPROVED->value => 'nao_aprovada',
                    bccomp((string) $row->quantity, '0', 8) <= 0 || bccomp((string) $row->unit_price, '0', 8) <= 0 => 'valor_invalido',
                    $row->billing_receipt_id && (int) $row->billing_receipt_id !== $currentReceiptId => 'ja_faturada',
                    default => 'incompativel',
                };
                $reasons[$reason] = ($reasons[$reason] ?? 0) + 1;
            }
        }

        return [
            'selected_ids' => $selected->all(),
            'candidate_count' => $candidateIds->count(),
            'excluded_count' => $excluded->count(),
            'reasons' => $reasons,
        ];
    }

    /** @return Collection<int, AssociateReceipt> */
    private function resolveAssociateReceipts(int $tenantId, array $codes): Collection
    {
        $tokens = collect($codes)->flatMap(fn ($code) => preg_split('/[\r\n,;]+/', (string) $code) ?: [])
            ->map(function ($code): string {
                $token = trim((string) $code);
                if (preg_match('/\b(CP-[A-Z0-9-]+)\b/i', $token, $reference)) {
                    return mb_strtoupper($reference[1]);
                }
                if (preg_match('/([0-9a-f]{8}-[0-9a-f-]{27,})/i', $token, $uuid)) {
                    return mb_strtolower($uuid[1]);
                }

                return $token;
            })->filter()->unique()->values();
        $ids = collect();
        $morphType = (new AssociateReceipt)->getMorphClass();
        $uuids = $tokens->map(function (string $token): ?string {
            if (Str::isUuid($token)) {
                return strtolower($token);
            }

            return preg_match('/([0-9a-f]{8}-[0-9a-f-]{27,})/i', $token, $match)
                ? strtolower($match[1])
                : null;
        })->filter()->unique()->values();
        $referenceCodes = $tokens->map(fn (string $token): string => mb_strtoupper($token))
            ->filter(fn (string $token): bool => str_starts_with($token, 'CP-'))->unique()->values();

        FinancialDocumentIdentity::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('documentable_type', $morphType)
            ->where(function (Builder $query) use ($referenceCodes, $uuids): void {
                if ($referenceCodes->isNotEmpty()) {
                    $query->whereIn('reference_code', $referenceCodes);
                }
                if ($uuids->isNotEmpty()) {
                    $method = $referenceCodes->isNotEmpty() ? 'orWhereIn' : 'whereIn';
                    $query->{$method}('public_id', $uuids);
                }
                if ($referenceCodes->isEmpty() && $uuids->isEmpty()) {
                    $query->whereRaw('1 = 0');
                }
            })
            ->pluck('documentable_id')
            ->each(fn ($id) => $ids->push((int) $id));

        $numberTokens = $tokens->map(fn (string $token): string => mb_strtoupper($token))
            ->reject(fn (string $token): bool => str_starts_with($token, 'CP-') || Str::isUuid($token) || str_contains($token, '/financial-documents/'))
            ->flip();
        if ($numberTokens->isNotEmpty()) {
            AssociateReceipt::withoutGlobalScopes()->where('tenant_id', $tenantId)
                ->with('project')
                ->get(['id', 'sales_project_id', 'receipt_year', 'receipt_number', 'receipt_label', 'tenant_receipt_year', 'tenant_receipt_number', 'project_receipt_year', 'project_receipt_number'])
                ->filter(fn (AssociateReceipt $receipt): bool => $numberTokens->has(mb_strtoupper($receipt->formatted_number)))
                ->each(fn (AssociateReceipt $receipt) => $ids->push((int) $receipt->id));
        }

        return AssociateReceipt::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)->whereIn('id', $ids->unique())
            ->get();
    }
}
