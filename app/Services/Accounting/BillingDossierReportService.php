<?php

namespace App\Services\Accounting;

use App\Enums\CustomerReceiptStatus;
use App\Enums\DeliveryStatus;
use App\Models\AssociateReceipt;
use App\Models\CloudDocument;
use App\Models\CustomerBillingReceipt;
use App\Models\Document;
use App\Models\ProductionDelivery;
use App\Services\TenantIdentityService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/** Ephemeral, current-state reporting. Does not issue identities, documents or payments. */
final class BillingDossierReportService
{
    public const TYPES = [
        'members' => ['title' => 'Relatório de Entregas por Associado', 'view' => 'pdf.billing-member-deliveries'],
        'acceptance' => ['title' => 'Termo de Recebimento e Aceitabilidade', 'view' => 'pdf.billing-acceptance-term'],
        'documents' => ['title' => 'Relação de Documentos do Faturamento', 'view' => 'pdf.billing-document-register'],
    ];

    public const CONTENT_SLOTS = [
        'cover' => 'Capa (página própria)', 'intro' => 'Após a identificação do contexto',
        'before_table' => 'Antes da relação de produtos/documentos',
        'after_table' => 'Após a relação de produtos/documentos',
        'closing' => 'Antes das assinaturas', 'back_cover' => 'Contracapa (página própria)',
    ];

    public static function isReportKey(?string $key): bool
    {
        return in_array($key, ['billing_member_deliveries', 'billing_acceptance_term', 'billing_document_register'], true);
    }

    public function capture(CustomerBillingReceipt $receipt, string $scope = 'billing', ?int $projectId = null): array
    {
        return DB::transaction(function () use ($receipt, $scope, $projectId): array {
            $tenantId = (int) $receipt->tenant_id;
            $receipt->load(['tenant', 'project', 'customer', 'organization']);
            $projectIds = $receipt->projectIds();
            if ($projectId && ! in_array($projectId, $projectIds, true)) abort(404);
            $projectIds = $projectId ? [$projectId] : $projectIds;
            $projects = $receipt->includedProjects()->whereIn('id', $projectIds);
            $selectedIds = collect($receipt->delivery_ids ?? [])->map(fn ($id) => (int) $id)->filter()->unique();
            $selected = ProductionDelivery::withoutGlobalScopes()->where('tenant_id', $tenantId)->whereNull('deleted_at')
                ->when($receipt->status === CustomerReceiptStatus::DRAFT,
                    fn ($query) => $query->whereIn('id', $selectedIds),
                    fn ($query) => $query->where('billing_receipt_id', $receipt->id))->get();
            $from = $receipt->from_date?->toDateString() ?: $selected->min('delivery_date')?->toDateString();
            $to = $receipt->to_date?->toDateString() ?: $selected->max('delivery_date')?->toDateString();
            if ($scope === 'project_period' && (! $from || ! $to)) {
                throw ValidationException::withMessages(['scope' => 'Defina o período do faturamento ou selecione distribuições antes de exportar por projeto/período.']);
            }
            $query = ProductionDelivery::withoutGlobalScopes()->where('tenant_id', $tenantId)->whereNull('deleted_at')
                ->whereIn('sales_project_id', $projectIds)->whereNotNull('parent_delivery_id')->where('status', DeliveryStatus::APPROVED->value)
                ->whereHas('customer', fn ($query) => $query->withoutGlobalScopes()->where('tenant_id', $tenantId)
                    ->when($receipt->customer_id, fn ($query) => $query->where('id', $receipt->customer_id),
                        fn ($query) => $query->where('organization_id', $receipt->organization_id)))
                ->when($scope === 'project_period', fn ($query) => $query->whereDate('delivery_date', '>=', $from)->whereDate('delivery_date', '<=', $to),
                    fn ($query) => $query->whereIn('id', $selected->pluck('id')))
                ->with([
                    'product' => fn ($query) => $query->withoutGlobalScopes()->where('tenant_id', $tenantId),
                    'associate' => fn ($query) => $query->withoutGlobalScopes()->where('tenant_id', $tenantId),
                    'customer' => fn ($query) => $query->withoutGlobalScopes()->where('tenant_id', $tenantId),
                    'salesProject' => fn ($query) => $query->withoutGlobalScopes()->where('tenant_id', $tenantId),
                    'parentDelivery' => fn ($query) => $query->withoutGlobalScopes()->where('tenant_id', $tenantId),
                ])->orderBy('delivery_date')->orderBy('id');
            $rows = $query->get();
            $names = app(TenantIdentityService::class)->namesForUsers($tenantId, $rows->pluck('associate.user_id')->filter());
            $warnings = [];
            $invalidParents = $rows->filter(fn ($row) => ! $row->parentDelivery || $row->parentDelivery->deleted_at
                || $row->parentDelivery->parent_delivery_id || (int) $row->parentDelivery->sales_project_id !== (int) $row->sales_project_id
                || (int) $row->parentDelivery->associate_id !== (int) $row->associate_id)->pluck('id');
            if ($invalidParents->isNotEmpty()) $warnings[] = 'Distribuições com origem que precisa de conferência: #'.$invalidParents->implode(', #').'. As quantidades refletem o cadastro atual, não uma confirmação física.';
            if ($scope === 'billing') {
                $expected = $receipt->status === CustomerReceiptStatus::DRAFT ? $selectedIds : $selected->pluck('id');
                $excluded = $expected->diff($rows->pluck('id'));
                if ($excluded->isNotEmpty()) $warnings[] = 'Não incluídas por ausência, exclusão, falta de aprovação ou contexto incompatível: #'.$excluded->implode(', #').'. Confira o dossiê.';
            }
            $members = $rows->groupBy('associate_id')->map(function ($group, $id) use ($names): array {
                $associate = $group->first()->associate;
                return [
                    'id' => (int) $id, 'name' => $names[$associate?->user_id] ?? $associate?->nickname ?? 'Associado não identificado',
                    'document' => $associate?->cpf_cnpj, 'registration' => $associate?->registration_number,
                    'caf' => $associate?->dap_caf, 'property' => $associate?->property_name,
                    'location' => collect([$associate?->city, $associate?->state])->filter()->implode('/'),
                    'products' => $this->groupProducts($group), 'count' => $group->count(), 'units' => $this->units($group),
                ];
            })->sortBy(fn ($member) => mb_strtolower(\Illuminate\Support\Str::ascii($member['name'])), SORT_NATURAL)->values()->all();
            $products = $this->groupProducts($rows);
            $capturedAt = now('America/Sao_Paulo');
            $documents = $this->documents($receipt, $selected, $names);
            return [
                'tenant' => $receipt->tenant, 'project' => $projects->count() === 1 ? $projects->first() : null,
                'billing' => ['id' => $receipt->id, 'number' => $receipt->formatted_number,
                    'recipient' => $receipt->recipient_name, 'customer_id' => $receipt->customer_id,
                    'organization_id' => $receipt->organization_id,
                    'recipient_document' => $receipt->customer?->cnpj ?: $receipt->organization?->cnpj,
                    'projects' => $projects->pluck('title')->implode(' · '),
                ],
                'scope' => $scope, 'scope_label' => $scope === 'billing' ? 'Distribuições atuais desta folha' : 'Projeto(s), destinatário e período da folha',
                'period' => ($from ? date('d/m/Y', strtotime($from)) : 'Não definido').' a '.($to ? date('d/m/Y', strtotime($to)) : 'Não definido'),
                'captured_at' => $capturedAt->format('d/m/Y H:i:s'),
                'members' => $members, 'customers' => $this->customerGroups($rows, $members),
                'products' => $products, 'commercial_products' => $this->groupProducts($rows, true), 'units' => $this->units($rows),
                'distribution_count' => $rows->count(), 'member_count' => count($members),
                'gross' => $rows->reduce(fn ($sum, $row) => bcadd($sum, bcmul((string) $row->quantity, (string) $row->unit_price, 8), 8), '0'),
                'documents' => $documents, 'document_totals' => self::summarizeDocuments($documents), 'warnings' => $warnings,
                'report_context' => ['sales_project_id' => $projectId ?: (count($projectIds) === 1 ? $projectIds[0] : null),
                    'customer_id' => $receipt->customer_id, 'organization_id' => $receipt->organization_id],
            ];
        });
    }

    /** Allocate consolidated client charges, including fixed fees, without charging them again per member. */
    private function customerGroups(Collection $rows, array $members): array
    {
        $allocated = [];
        foreach ($rows->groupBy('sales_project_id') as $projectRows) {
            $financial = app(\App\Services\CustomerBillingLineConsolidator::class)
                ->consolidate($projectRows, $projectRows->first()->salesProject);
            foreach ($financial['lines'] as $line) {
                $groups = $projectRows->whereIn('id', $line['distribution_ids'])
                    ->groupBy(fn ($row) => $row->customer_id.'|'.$row->associate_id)->values();
                $remainingGross = $line['document_gross'];
                $remainingFees = array_column($line['fees'], 'amount');
                foreach ($groups as $index => $group) {
                    $first = $group->first();
                    $quantity = $group->reduce(fn ($sum, $row) => bcadd($sum, (string) $row->quantity, 8), '0');
                    $share = fn ($amount) => bccomp($line['quantity'], '0', 8) !== 0
                        ? bcdiv(bcmul($amount, $quantity, 12), $line['quantity'], 2) : '0.00';
                    $last = $index === $groups->count() - 1;
                    $gross = $last ? $remainingGross : $share($line['document_gross']);
                    $remainingGross = bcsub($remainingGross, $gross, 2);
                    $fees = [];
                    $discounts = $accruals = '0.00';
                    foreach ($line['fees'] as $feeIndex => $fee) {
                        $amount = $last ? $remainingFees[$feeIndex] : $share($fee['amount']);
                        $remainingFees[$feeIndex] = bcsub($remainingFees[$feeIndex], $amount, 2);
                        $fees[] = array_merge($fee, ['amount' => $amount]);
                        if (($fee['nature'] ?? 'discount') === 'accrual') $accruals = bcadd($accruals, $amount, 2);
                        else $discounts = bcadd($discounts, $amount, 2);
                    }
                    $allocated[$first->customer_id][$first->associate_id][] = [
                        'name' => $line['product'], 'project' => $line['project'], 'unit' => $line['unit'],
                        'quantity' => $quantity, 'unit_price' => $line['unit_price'], 'gross' => $gross,
                        'fees' => $fees, 'discounts' => $discounts, 'accruals' => $accruals,
                        'net' => bcsub(bcadd($gross, $accruals, 2), $discounts, 2), 'count' => $group->count(),
                        'from' => $group->min('delivery_date')?->format('d/m/Y'), 'to' => $group->max('delivery_date')?->format('d/m/Y'),
                    ];
                }
            }
        }
        return $rows->groupBy('customer_id')->map(function ($group, $id) use ($members, $allocated): array {
            $customer = $group->first()->customer;
            $clientMembers = collect($members)->whereIn('id', $group->pluck('associate_id')->all())
                ->map(function ($member) use ($allocated, $id, $group): array {
                    $memberRows = $group->where('associate_id', $member['id']);
                    return array_merge($member, ['products' => collect($allocated[$id][$member['id']] ?? [])
                        ->sortBy(fn ($product) => mb_strtolower(\Illuminate\Support\Str::ascii($product['name'].' '.$product['project'])).'|'.$product['unit_price'])->values()->all(),
                        'count' => $memberRows->count(), 'units' => $this->units($memberRows)]);
                })->values()->all();
            $products = collect($clientMembers)->pluck('products')->flatten(1);
            $totals = [];
            foreach (['gross', 'discounts', 'accruals', 'net'] as $field) {
                $totals[$field] = $products->reduce(fn ($sum, $product) => bcadd($sum, $product[$field], 2), '0.00');
            }
            return ['id' => (int) $id, 'name' => $customer?->name ?: 'Cliente não identificado',
                'document' => $customer?->cnpj, 'projects' => $group->pluck('salesProject.title')->unique()->implode(' · '),
                'members' => $clientMembers, 'units' => $this->units($group), 'totals' => $totals];
        })->sortBy(fn ($customer) => mb_strtolower(\Illuminate\Support\Str::ascii($customer['name'])), SORT_NATURAL)->values()->all();
    }

    private function groupProducts(Collection $rows, bool $separatePrices = false): array
    {
        return $rows->groupBy(fn ($row) => $row->sales_project_id.'|'.$row->product_id.'|'.mb_strtolower(trim($row->product?->unit ?: 'un'))
                .($separatePrices ? '|'.bcadd((string) $row->unit_price, '0', 8) : ''))
            ->map(function ($group) use ($separatePrices): array {
                $first = $group->first();
                $quantity = $group->reduce(fn ($sum, $row) => bcadd($sum, (string) $row->quantity, 8), '0');
                $gross = $group->reduce(fn ($sum, $row) => bcadd($sum, bcmul((string) $row->quantity, (string) $row->unit_price, 8), 8), '0');
                return ['name' => $first->product?->name ?: 'Produto #'.$first->product_id, 'unit' => trim($first->product?->unit ?: 'un'),
                    'project' => $first->salesProject?->title ?: 'Projeto #'.$first->sales_project_id,
                    'quantity' => $quantity, 'gross' => $gross, 'count' => $group->count(),
                    'unit_price' => $separatePrices ? (string) $first->unit_price : (bccomp($quantity, '0', 8) > 0 ? bcdiv($gross, $quantity, 8) : '0'),
                    'from' => $group->min('delivery_date')?->format('d/m/Y'), 'to' => $group->max('delivery_date')?->format('d/m/Y')];
            })->sortBy(fn ($row) => mb_strtolower(\Illuminate\Support\Str::ascii($row['name'].' '.$row['project'])))->values()->all();
    }

    private function units(Collection $rows): array
    {
        return $rows->groupBy(fn ($row) => mb_strtolower(trim($row->product?->unit ?: 'un')))->map(fn ($group) => [
            'unit' => trim($group->first()->product?->unit ?: 'un'),
            'quantity' => $group->reduce(fn ($sum, $row) => bcadd($sum, (string) $row->quantity, 8), '0'),
        ])->sortKeys()->values()->all();
    }

    /** Nominal document values, not payment balances; never add different document types together. */
    public static function summarizeDocuments(array $documents): array
    {
        return collect($documents)->groupBy('type')->map(function ($group, $type): array {
            $valued = $group->filter(fn ($document) => ($document['amount'] ?? null) !== null);
            return ['type' => $type, 'count' => $group->count(), 'missing' => $group->count() - $valued->count(),
                'amount' => $valued->isEmpty() ? null : $valued->reduce(
                    fn ($sum, $document) => bcadd($sum, \App\Support\FinancialAmount::cents($document['amount']), 2), '0.00')];
        })->values()->all();
    }

    private function documents(CustomerBillingReceipt $billing, Collection $selected, array $names): array
    {
        $tenantId = (int) $billing->tenant_id;
        $memberIds = $selected->pluck('associate_receipt_id')->filter()->unique();
        $members = AssociateReceipt::withoutGlobalScopes()->where('tenant_id', $tenantId)
            ->where(fn ($query) => $query->whereIn('sales_project_id', $billing->projectIds())->orWhereIn('id', $memberIds))
            ->with(['associate' => fn ($query) => $query->withoutGlobalScopes()->where('tenant_id', $tenantId),
                'project' => fn ($query) => $query->withoutGlobalScopes()->where('tenant_id', $tenantId)])
            ->get()->filter(fn ($member) => $memberIds->contains($member->id)
                || collect($member->delivery_ids)->intersect($selected->pluck('id'))->isNotEmpty());
        $names += app(TenantIdentityService::class)->namesForUsers($tenantId, $members->pluck('associate.user_id')->filter());
        $owners = [$billing->getMorphClass().'|'.$billing->id => $billing->recipient_name];
        $result = [['type' => 'Folha de faturamento', 'number' => $billing->formatted_number, 'owner' => $billing->recipient_name,
            'status' => $billing->status?->getLabel(), 'date' => $billing->issued_at?->format('d/m/Y'), 'count' => $selected->count(),
            'amount' => $billing->total_net === null ? null : \App\Support\FinancialAmount::cents($billing->total_net)]];
        foreach ($members as $member) {
            $name = $names[$member->associate?->user_id] ?? $member->associate?->nickname ?? 'Associado não identificado';
            $owners[$member->getMorphClass().'|'.$member->id] = $name;
            $result[] = ['type' => 'Comprovante do associado', 'number' => $member->formatted_number,
                'owner' => $name, 'status' => $member->status?->getLabel(), 'date' => $member->issued_at?->format('d/m/Y'),
                'amount' => $member->total_net === null ? null : \App\Support\FinancialAmount::cents($member->total_net),
                'count' => $selected->where('associate_receipt_id', $member->id)->pluck('id')
                    ->merge(collect($member->delivery_ids)->intersect($selected->pluck('id')))->unique()->count()];
        }
        // Attachments may belong to a payment record rather than directly to its receipt.
        foreach ([\App\Models\CustomerReceiptPayment::class => 'customer_billing_receipt_id',
            \App\Models\AssociateReceiptPayment::class => 'associate_receipt_id'] as $paymentModel => $foreignKey) {
            if (! Schema::hasTable((new $paymentModel)->getTable())) continue;
            $customerPayment = $foreignKey === 'customer_billing_receipt_id';
            $payments = $paymentModel::withoutGlobalScopes()->where('tenant_id', $tenantId)
                ->whereIn($foreignKey, $customerPayment ? [$billing->id] : $members->pluck('id'))->get(['id', $foreignKey]);
            foreach ($payments as $payment) {
                $owners[$payment->getMorphClass().'|'.$payment->id] = $customerPayment ? $billing->recipient_name
                    : ($owners[(new AssociateReceipt)->getMorphClass().'|'.$payment->associate_receipt_id] ?? 'Não identificado');
            }
        }
        $ownerGroups = collect(array_keys($owners))->groupBy(fn ($key) => explode('|', $key)[0])
            ->map(fn ($keys) => $keys->map(fn ($key) => (int) explode('|', $key)[1])->all());
        foreach ([Document::class => 'Anexo', CloudDocument::class => 'Arquivo no Drive'] as $model => $label) {
            if (! Schema::hasTable((new $model)->getTable())) continue;
            $query = $model::withoutGlobalScopes()->where('tenant_id', $tenantId)
                ->where(function ($query) use ($ownerGroups): void {
                    foreach ($ownerGroups as $type => $ids) {
                        $query->orWhere(fn ($query) => $query->where('documentable_type', $type)->whereIn('documentable_id', $ids));
                    }
                });
            if ($model === Document::class) $query->whereNull('deleted_at');
            foreach ($query->get() as $file) {
                $result[] = ['type' => $label, 'number' => $file->name ?: $file->original_name ?: basename((string) $file->remote_path),
                    'owner' => $owners[$file->documentable_type.'|'.$file->documentable_id] ?? 'Não identificado',
                    'status' => $model === CloudDocument::class ? match ($file->status) {'synced' => 'Sincronizado', 'pending' => 'Pendente', default => 'Verificar'} : 'Anexado',
                    'date' => ($file->synced_at ?? $file->document_date ?? $file->created_at)?->format('d/m/Y'), 'count' => null,
                    'amount' => null];
            }
        }
        return $result;
    }
}
