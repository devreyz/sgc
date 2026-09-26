<?php

namespace App\Services;

use App\Enums\DeliveryStatus;
use App\Models\AssociateReceipt;
use App\Models\Customer;
use App\Models\FinancialDocumentIdentity;
use App\Models\ProductionDelivery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/** Resolve selecao de faturamento sem usar o total do comprovante do associado. */
final class CustomerBillingSelectionService
{
    public function eligibleQuery(
        int $tenantId,
        array $projectIds,
        ?int $customerId,
        ?int $organizationId,
        ?string $from,
        ?string $to,
        ?int $currentReceiptId = null,
    ): Builder {
        $query = ProductionDelivery::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereIn('sales_project_id', collect($projectIds)->map(fn ($id): int => (int) $id)->filter()->unique())
            ->whereNotNull('parent_delivery_id')
            ->where('status', DeliveryStatus::APPROVED->value)
            ->where('quantity', '>', 0)
            ->where('unit_price', '>', 0)
            ->where(function (Builder $billing) use ($currentReceiptId): void {
                $billing->whereNull('billing_receipt_id');
                if ($currentReceiptId) {
                    $billing->orWhere('billing_receipt_id', $currentReceiptId);
                }
            });

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
            ->whereIn('associate_receipt_id', $receipts->pluck('id'))
            ->pluck('id')->map(fn ($id): int => (int) $id)->unique()->values();

        $selection = $this->selectDistributionIds(
            $tenantId, $candidateIds->all(), $projectIds, $customerId, $organizationId, $from, $to, $currentReceiptId,
        );

        return $selection + ['receipt_count' => $receipts->count()];
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
            $rows = ProductionDelivery::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)->whereIn('id', $excluded)
                ->get(['id', 'sales_project_id', 'customer_id', 'parent_delivery_id', 'delivery_date', 'quantity', 'unit_price', 'status', 'billing_receipt_id']);
            $organizationCustomerIds = $organizationId
                ? Customer::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('organization_id', $organizationId)->pluck('id')->map(fn ($id): int => (int) $id)->all()
                : [];
            $foundIds = $rows->pluck('id')->map(fn ($id): int => (int) $id);
            $missingCount = $excluded->diff($foundIds)->count();
            if ($missingCount > 0) {
                $reasons['nao_encontrada_no_tenant'] = $missingCount;
            }
            foreach ($rows as $row) {
                $reason = match (true) {
                    ! in_array((int) $row->sales_project_id, array_map('intval', $projectIds), true) => 'outro_projeto',
                    $customerId && (int) $row->customer_id !== $customerId => 'outro_destinatario',
                    $organizationId && ! in_array((int) $row->customer_id, $organizationCustomerIds, true) => 'outro_destinatario',
                    $from && $row->delivery_date?->format('Y-m-d') < $from => 'fora_do_periodo',
                    $to && $row->delivery_date?->format('Y-m-d') > $to => 'fora_do_periodo',
                    ! $row->parent_delivery_id => 'nao_e_distribuicao',
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
