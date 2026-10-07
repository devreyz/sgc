<?php

namespace App\Http\Controllers\Accounting;

use App\Enums\BillingAuthorizationStatus;
use App\Enums\CustomerReceiptStatus;
use App\Enums\DeliveryStatus;
use App\Exceptions\BillingAuthorizationBlockedException;
use App\Http\Controllers\Controller;
use App\Models\AssociateReceipt;
use App\Models\BillingAuthorization;
use App\Models\Customer;
use App\Models\CustomerBillingReceipt;
use App\Models\Document;
use App\Models\FinancialDocumentIdentity;
use App\Models\Organization;
use App\Models\OrganizationAuthorizedEmail;
use App\Models\ProductionDelivery;
use App\Models\SalesProject;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Accounting\AccountingAccessService;
use App\Services\Accounting\AccountingNextActionResolver;
use App\Services\Accounting\AccountingProcessIntegrityService;
use App\Services\Accounting\BillingAuthorizationValidityService;
use App\Services\Accounting\BillingAuthorizationWorkflowService;
use App\Services\Accounting\FiscalGateService;
use App\Services\DeliveryParentRecoveryService;
use App\Services\TenantIdentityService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AccountingPortalController extends Controller
{
    public function index(Request $request): View
    {
        $tenant = $this->tenant($request);
        $this->authorizePortal($request);

        return view('accounting.index', compact('tenant'));
    }

    public function processes(Request $request): View
    {
        $tenant = $this->tenant($request);
        $this->authorizeProcesses($request);

        return view('accounting.processes.index', compact('tenant'));
    }

    public function sourceReceipts(Request $request): View
    {
        $tenant = $this->tenant($request);
        $this->authorizeProcesses($request);

        return view('accounting.source-receipts.index', compact('tenant'));
    }

    public function sourceReceiptsData(Request $request, TenantIdentityService $identities): JsonResponse
    {
        $tenant = $this->tenant($request);
        $this->authorizeProcesses($request);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:180'],
            'project' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'in:draft,pending_payment,partially_paid,paid,obsolete,cancelled'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'between:10,50'],
        ]);

        $search = trim((string) ($filters['search'] ?? ''));
        $identityIds = collect();
        if ($search !== '') {
            $uuid = Str::isUuid($search) ? $search : (preg_match('/([0-9a-f]{8}-[0-9a-f-]{27,})/i', $search, $matches) ? $matches[1] : null);
            $reference = preg_match('/\b(CP-[A-Z0-9-]+)\b/i', $search, $matches) ? mb_strtoupper($matches[1]) : null;
            if ($uuid || $reference) {
                $identityIds = FinancialDocumentIdentity::withoutGlobalScopes()
                    ->where('tenant_id', $tenant->id)
                    ->where('documentable_type', (new AssociateReceipt)->getMorphClass())
                    ->where(function (Builder $query) use ($uuid, $reference): void {
                        $query->when($uuid, fn (Builder $query, string $value) => $query->where('public_id', mb_strtolower($value)))
                            ->when($reference, fn (Builder $query, string $value) => $query->orWhere('reference_code', $value));
                    })->pluck('documentable_id');
            }
        }

        $visibleReceiptIds = $this->billingSourceReceiptIds($tenant->id, $request->user());

        $query = AssociateReceipt::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->whereIn('id', $visibleReceiptIds)
            ->with([
                'project:id,tenant_id,title,code,receipt_numbering_scope,receipt_number_format,receipt_project_reference',
                'associate:id,tenant_id,user_id,nickname',
                'verificationIdentity',
            ])->withCount('distributions');

        $query->when($filters['project'] ?? null, fn (Builder $query, int $projectId) => $query->where('sales_project_id', $projectId))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($search !== '', function (Builder $query) use ($search, $identityIds): void {
                $number = preg_match('/\b0*(\d+)\s*\/\s*\d{4}/', $search, $numberMatch)
                    ? (int) $numberMatch[1]
                    : (ctype_digit($search) ? (int) $search : null);
                $query->where(function (Builder $nested) use ($search, $number, $identityIds): void {
                    $nested->whereIn('id', $identityIds)
                        ->orWhere('receipt_label', 'like', '%'.$search.'%')
                        ->orWhereHas('project', fn (Builder $project) => $project
                            ->where('title', 'like', '%'.$search.'%')->orWhere('code', 'like', '%'.$search.'%'))
                        ->orWhereHas('associate', fn (Builder $associate) => $associate
                            ->where('nickname', 'like', '%'.$search.'%')
                            ->orWhereHas('user', fn (Builder $user) => $user->where('name', 'like', '%'.$search.'%')));
                    if ($number !== null) {
                        $nested->orWhere('receipt_number', $number)
                            ->orWhere('tenant_receipt_number', $number)
                            ->orWhere('project_receipt_number', $number);
                    }
                });
            });

        $receipts = $query->latest('issued_at')->latest('id')
            ->paginate((int) ($filters['per_page'] ?? 20))->withQueryString();
        $names = $identities->namesForUsers($tenant->id, $receipts->getCollection()->pluck('associate.user_id')->filter());
        $receipts->getCollection()->transform(fn (AssociateReceipt $receipt): array => $this->sourceReceiptRow($receipt, $tenant->slug, $names));

        return $this->privateJson([
            'receipts' => $receipts,
            'filters' => ['projects' => SalesProject::withoutGlobalScopes()->where('tenant_id', $tenant->id)
                ->whereIn('id', AssociateReceipt::withoutGlobalScopes()->where('tenant_id', $tenant->id)
                    ->whereIn('id', $visibleReceiptIds)->distinct()->pluck('sales_project_id'))
                ->orderByDesc('reference_year')->orderBy('title')->get(['id', 'title', 'code'])
                ->map(fn (SalesProject $project) => ['id' => $project->id, 'label' => $project->title.($project->code ? ' · '.$project->code : '')])],
        ]);
    }

    public function sourceReceiptData(Request $request, TenantIdentityService $identities): JsonResponse
    {
        $tenant = $this->tenant($request);
        $this->authorizeProcesses($request);
        $visibleReceiptIds = $this->billingSourceReceiptIds($tenant->id, $request->user());
        $receipt = AssociateReceipt::withoutGlobalScopes()->where('tenant_id', $tenant->id)
            ->whereIn('id', $visibleReceiptIds)
            ->with(['project', 'associate:id,tenant_id,user_id,nickname', 'verificationIdentity'])
            ->findOrFail((int) $request->route('associateReceipt'));
        $names = $identities->namesForUsers($tenant->id, collect([$receipt->associate?->user_id])->filter());

        $distributions = ProductionDelivery::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)->where('associate_receipt_id', $receipt->id)
            ->with(['product:id,tenant_id,name,unit', 'customer:id,tenant_id,name,trade_name', 'parentDelivery:id,tenant_id,delivery_date'])
            ->orderByDesc('delivery_date')->orderByDesc('id')->get()
            ->map(fn (ProductionDelivery $row): array => [
                'id' => $row->id,
                'date' => $row->delivery_date?->format('d/m/Y'),
                'product' => $row->product?->name ?? 'Produto não identificado',
                'unit' => $row->product?->unit ?: 'un',
                'customer' => $row->customer?->trade_name ?: $row->customer?->name ?: 'Destino não identificado',
                'quantity' => (float) $row->quantity,
                'unit_price' => (float) $row->unit_price,
                'gross' => (float) $row->gross_value,
                'net' => (float) $row->net_value,
                'billing_receipt_id' => $row->billing_receipt_id ? (int) $row->billing_receipt_id : null,
            ]);
        $processIds = $distributions->pluck('billing_receipt_id')->filter()->unique();
        $processes = app(AccountingAccessService::class)->scopeBillings(
            CustomerBillingReceipt::withoutGlobalScopes()->where('tenant_id', $tenant->id),
            $request->user(),
            $tenant->id,
        )->whereIn('id', $processIds)
            ->get()->map(fn (CustomerBillingReceipt $process): array => [
                'id' => $process->id,
                'number' => $process->formatted_number,
                'status' => $process->status?->getLabel() ?? 'Rascunho',
                'url' => route('accounting.processes.show', ['tenant' => $tenant->slug, 'receipt' => $process->id]),
            ]);

        return $this->privateJson([
            'receipt' => $this->sourceReceiptRow($receipt, $tenant->slug, $names),
            'distributions' => $distributions,
            'processes' => $processes,
        ]);
    }

    public function show(Request $request): View
    {
        $tenant = $this->tenant($request);
        $this->authorizeProcesses($request);
        $receipt = $this->receipt($request, $tenant->id);

        return view('accounting.processes.show', [
            'tenant' => $tenant,
            'receiptId' => $receipt->id,
            'receiptNumber' => $receipt->formatted_number,
        ]);
    }

    public function queue(Request $request): JsonResponse
    {
        $tenant = $this->tenant($request);
        $this->authorizePortal($request);
        $base = app(AccountingAccessService::class)->scopeBillings(
            CustomerBillingReceipt::query()->where('tenant_id', $tenant->id),
            $request->user(),
            $tenant->id,
        );

        $critical = $this->criticalProcessQuery(clone $base)->count();

        $validBase = $this->structurallyValidQuery(clone $base);
        $drafts = $this->draftPreparationQuery(clone $base)->count();
        $closed = (clone $validBase)->where('status', CustomerReceiptStatus::PENDING_PAYMENT->value)
            ->whereDoesntHave('authorizationRounds')->count();
        $partial = (clone $validBase)->where('status', CustomerReceiptStatus::PARTIALLY_PAID->value)->count();

        $items = collect([
            $this->queueItem('critical', 'Erros de integridade', $critical, 'alert-triangle', 'danger', [
                'pending' => 'review_inconsistency',
            ]),
            $this->queueItem('drafts', 'Rascunhos para completar', $drafts, 'file-pen-line', 'neutral', [
                'financial_status' => CustomerReceiptStatus::DRAFT->value,
            ]),
            $this->queueItem('closed', 'Aguardando envio para autorização', $closed, 'clipboard-check', 'warning', [
                'financial_status' => CustomerReceiptStatus::PENDING_PAYMENT->value,
            ]),
            $this->queueItem('awaiting_authorization', 'Aguardando organização', (clone $validBase)
                ->whereHas('latestAuthorizationRound', fn (Builder $query) => $query->where('status', BillingAuthorizationStatus::SENT->value))->count(), 'clock-3', 'warning', [
                    'authorization_status' => BillingAuthorizationStatus::SENT->value,
                ]),
            $this->queueItem('correction_requested', 'Correções solicitadas', (clone $validBase)
                ->whereHas('latestAuthorizationRound', fn (Builder $query) => $query->where('status', BillingAuthorizationStatus::CORRECTION_REQUESTED->value))->count(), 'message-square-warning', 'danger', [
                    'authorization_status' => BillingAuthorizationStatus::CORRECTION_REQUESTED->value,
                ]),
            $this->queueItem('authorization_invalidated', 'Autorizações invalidadas', (clone $validBase)
                ->whereHas('latestAuthorizationRound', fn (Builder $query) => $query->where('status', BillingAuthorizationStatus::INVALIDATED->value))->count(), 'shield-alert', 'danger', [
                    'authorization_status' => BillingAuthorizationStatus::INVALIDATED->value,
                ]),
            $this->queueItem('authorized', 'Autorizadas — preparar emissão', (clone $validBase)
                ->whereHas('latestAuthorizationRound', fn (Builder $query) => $query->where('status', BillingAuthorizationStatus::AUTHORIZED->value))->count(), 'badge-check', 'success', [
                    'authorization_status' => BillingAuthorizationStatus::AUTHORIZED->value,
                ]),
            $this->queueItem('partial', 'Recebimentos parciais', $partial, 'circle-dollar-sign', 'info', [
                'financial_status' => CustomerReceiptStatus::PARTIALLY_PAID->value,
            ]),
        ])->filter(fn (array $item) => $item['count'] > 0)->values();

        $openAmount = (float) (clone $base)
            ->whereIn('status', [
                CustomerReceiptStatus::PENDING_PAYMENT->value,
                CustomerReceiptStatus::PARTIALLY_PAID->value,
            ])
            ->selectRaw('COALESCE(SUM(CASE WHEN COALESCE(total_net, 0) > COALESCE(amount_paid, 0) THEN COALESCE(total_net, 0) - COALESCE(amount_paid, 0) ELSE 0 END), 0) AS aggregate')
            ->value('aggregate');

        return $this->privateJson([
            'queue' => $items,
            'summary' => [
                'open_processes' => $closed + $partial,
                'open_amount' => $openAmount,
                'workflow_label' => 'Preparação → autorização → emissão → recebimento → prestação de contas',
            ],
            'empty' => $items->isEmpty(),
        ]);
    }

    public function processesData(Request $request, AccountingNextActionResolver $resolver, FiscalGateService $fiscalGate): JsonResponse
    {
        $tenant = $this->tenant($request);
        $this->authorizeProcesses($request);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'project' => ['nullable', 'integer', 'min:1'],
            'organization' => ['nullable', 'integer', 'min:1'],
            'customer' => ['nullable', 'integer', 'min:1'],
            'from' => ['nullable', 'date'],
            'until' => ['nullable', 'date', 'after_or_equal:from'],
            'financial_status' => ['nullable', 'in:draft,pending_payment,partially_paid,paid'],
            'authorization_status' => ['nullable', 'in:legacy_unsubmitted,sent,authorized,correction_requested,invalidated,cancelled'],
            'fiscal_status' => ['nullable', 'in:not_started'],
            'accountability_status' => ['nullable', 'in:not_started'],
            'pending' => ['nullable', 'in:review_inconsistency,review_draft,review_closed,track_balance,view_dossier'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'between:10,50'],
        ]);

        $query = app(AccountingAccessService::class)->scopeBillings(
            $this->processQuery($tenant->id),
            $request->user(),
            $tenant->id,
        );
        $search = trim((string) ($filters['search'] ?? ''));

        $query
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $nested) use ($search): void {
                    $nested->where('receipt_label', 'like', '%'.$search.'%')
                        ->orWhere('receipt_number', $search)
                        ->orWhereHas('project', fn (Builder $project) => $project
                            ->where('title', 'like', '%'.$search.'%')
                            ->orWhere('code', 'like', '%'.$search.'%'))
                        ->orWhereHas('customer', fn (Builder $customer) => $customer
                            ->where('name', 'like', '%'.$search.'%')
                            ->orWhere('trade_name', 'like', '%'.$search.'%'))
                        ->orWhereHas('organization', fn (Builder $organization) => $organization
                            ->where('name', 'like', '%'.$search.'%'));
                });
            })
            ->when($filters['project'] ?? null, fn (Builder $query, int $id) => $query->where('sales_project_id', $id))
            ->when($filters['organization'] ?? null, fn (Builder $query, int $id) => $query->where('organization_id', $id))
            ->when($filters['customer'] ?? null, fn (Builder $query, int $id) => $query->where('customer_id', $id))
            ->when($filters['from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('issued_at', '>=', $date))
            ->when($filters['until'] ?? null, fn (Builder $query, string $date) => $query->whereDate('issued_at', '<=', $date))
            ->when($filters['financial_status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status));

        if (($filters['authorization_status'] ?? null) === 'legacy_unsubmitted') {
            $query->whereDoesntHave('authorizationRounds');
        } elseif ($authorizationStatus = $filters['authorization_status'] ?? null) {
            $query->whereHas('latestAuthorizationRound', fn (Builder $round) => $round->where('status', $authorizationStatus));
        }

        if (($filters['pending'] ?? null) === 'review_inconsistency') {
            $this->criticalProcessQuery($query);
        } elseif ($pending = $filters['pending'] ?? null) {
            $status = match ($pending) {
                'review_draft' => CustomerReceiptStatus::DRAFT->value,
                'review_closed' => CustomerReceiptStatus::PENDING_PAYMENT->value,
                'track_balance' => CustomerReceiptStatus::PARTIALLY_PAID->value,
                'view_dossier' => CustomerReceiptStatus::PAID->value,
                default => null,
            };
            $query->when($status, fn (Builder $query) => $query->where('status', $status));
        }

        $processes = $query
            ->latest('issued_at')
            ->latest('id')
            ->paginate((int) ($filters['per_page'] ?? 20))
            ->withQueryString();

        $processes->getCollection()->transform(
            fn (CustomerBillingReceipt $receipt) => $this->processRow($receipt, $resolver, $fiscalGate, $tenant->slug)
        );

        return $this->privateJson([
            'processes' => $processes,
            'filters' => $this->filterOptions($tenant->id, $request->user()),
        ]);
    }

    public function processData(
        Request $request,
        AccountingNextActionResolver $resolver,
        AccountingProcessIntegrityService $integrity,
        BillingAuthorizationValidityService $authorizationValidity,
        FiscalGateService $fiscalGate,
        TenantIdentityService $identities,
        DeliveryParentRecoveryService $recovery,
    ): JsonResponse {
        $tenant = $this->tenant($request);
        $this->authorizeProcesses($request);
        $request->validate([
            'distributions_page' => ['nullable', 'integer', 'min:1'],
            'producer_receipts_page' => ['nullable', 'integer', 'min:1'],
        ]);
        $receipt = $this->receipt($request, $tenant->id);
        $receipt->load([
            'project:id,tenant_id,title,code,type,status,start_date,end_date,receipt_numbering_scope,receipt_number_format,receipt_project_reference',
            'customer:id,tenant_id,name,trade_name,organization_id',
            'organization:id,tenant_id,name,email,responsible_name',
            'bankAccount:id,tenant_id,name,type',
        ]);

        $integrityResult = $integrity->inspect($receipt);
        $recoveryDiagnosis = $recovery->diagnosisForCustomerReceipt($receipt);
        $integrityResult['repair_url'] = $recoveryDiagnosis['recoverable'] > 0
            && $request->user()->can('review_accounting_processes')
                ? route('accounting.data.processes.integrity.repair', [
                    'tenant' => $tenant->slug,
                    'receipt' => $receipt->id,
                ])
                : null;
        $receipt->load(['authorizationRounds' => fn ($query) => $query->latest('sequence')->limit(50)]);
        $latestRound = $receipt->authorizationRounds->first();
        $isCurrentAuthorizationValid = $latestRound?->status === BillingAuthorizationStatus::AUTHORIZED
            ? $authorizationValidity->isValid($receipt, $latestRound)
            : null;
        $authorization = $this->authorizationPayload($latestRound, $isCurrentAuthorizationValid);
        $authorization['access'] = $this->authorizationAccessPayload($receipt, $request);
        $fiscal = $receipt->status !== CustomerReceiptStatus::DRAFT
            ? $fiscalGate->evaluate($receipt, $tenant->id)
            : null;
        $state = $resolver->resolve(
            $receipt->status,
            $integrityResult['critical_count'],
            $authorization['state'],
            $fiscal,
            $integrityResult['preparation_count'],
        );
        $draftDistributionIds = $receipt->status === CustomerReceiptStatus::DRAFT
            ? collect($receipt->delivery_ids)->map(fn ($id): int => (int) $id)->filter()->unique()->values()
            : collect();
        $distributionQuery = ProductionDelivery::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->whereNull('deleted_at')
            ->when(
                $draftDistributionIds->isNotEmpty(),
                fn (Builder $query) => $query->whereIn('id', $draftDistributionIds),
                fn (Builder $query) => $query->where('billing_receipt_id', $receipt->id),
            );
        $distributions = (clone $distributionQuery)
            ->with([
                'product:id,tenant_id,name,unit',
                'customer:id,tenant_id,name,trade_name',
                'associate:id,tenant_id,user_id,nickname',
                'parentDelivery:id,tenant_id,delivery_date,quantity',
            ])
            ->orderByDesc('delivery_date')
            ->orderByDesc('id')
            ->paginate(25, ['*'], 'distributions_page');

        $names = $identities->namesForUsers(
            $tenant->id,
            $distributions->getCollection()->pluck('associate.user_id')->filter(),
        );

        $distributions->getCollection()->transform(function (ProductionDelivery $distribution) use ($names): array {
            return [
                'id' => $distribution->id,
                'date' => $distribution->delivery_date?->format('d/m/Y'),
                'product' => $distribution->product?->name ?? 'Produto não identificado',
                'unit' => $distribution->product?->unit ?: 'un',
                'customer' => $distribution->customer?->trade_name ?: $distribution->customer?->name ?: 'Cliente não identificado',
                'member' => $names[$distribution->associate?->user_id]
                    ?? $distribution->associate?->nickname
                    ?? 'Membro não identificado',
                'quantity' => (float) $distribution->quantity,
                'unit_price' => (float) $distribution->unit_price,
                'gross_value' => (float) $distribution->gross_value,
                'fees' => (float) $distribution->admin_fee_amount,
                'net_value' => (float) $distribution->net_value,
                'parent' => $distribution->parentDelivery ? [
                    'id' => $distribution->parentDelivery->id,
                    'date' => $distribution->parentDelivery->delivery_date?->format('d/m/Y'),
                    'received_quantity' => (float) $distribution->parentDelivery->quantity,
                ] : null,
            ];
        });

        $producerReceiptIds = (clone $distributionQuery)
            ->whereNotNull('associate_receipt_id')
            ->distinct()
            ->pluck('associate_receipt_id');
        $includedByProducerReceipt = (clone $distributionQuery)
            ->whereNotNull('associate_receipt_id')
            ->selectRaw('associate_receipt_id, COUNT(*) AS aggregate')
            ->groupBy('associate_receipt_id')
            ->pluck('aggregate', 'associate_receipt_id');
        $producerReceipts = AssociateReceipt::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('id', $producerReceiptIds)
            ->with([
                'associate:id,tenant_id,user_id,nickname',
                'project:id,tenant_id,title,receipt_numbering_scope,receipt_number_format,receipt_project_reference',
            ])
            ->orderByDesc('issued_at')
            ->paginate(20, [
                'id', 'tenant_id', 'sales_project_id', 'associate_id', 'receipt_year', 'receipt_number',
                'receipt_label', 'tenant_receipt_year', 'tenant_receipt_number', 'project_receipt_year',
                'project_receipt_number', 'issued_at', 'status', 'total_net', 'amount_paid',
            ], 'producer_receipts_page');
        $producerNames = $identities->namesForUsers(
            $tenant->id,
            $producerReceipts->getCollection()->pluck('associate.user_id')->filter(),
        );
        $producerReceipts->getCollection()->transform(fn (AssociateReceipt $producerReceipt) => [
            'id' => $producerReceipt->id,
            'number' => $producerReceipt->formatted_number,
            'member' => $producerNames[$producerReceipt->associate?->user_id]
                ?? $producerReceipt->associate?->nickname
                ?? 'Membro não identificado',
            'issued_at' => $producerReceipt->issued_at?->format('d/m/Y'),
            'status' => $producerReceipt->status?->value,
            'status_label' => $producerReceipt->status?->getLabel() ?? 'Rascunho',
            'net' => (float) $producerReceipt->total_net,
            'paid' => (float) $producerReceipt->amount_paid,
            'included_distributions' => (int) ($includedByProducerReceipt[$producerReceipt->id] ?? 0),
            'detail_url' => route('accounting.data.source-receipts.show', [
                'tenant' => $tenant->slug, 'associateReceipt' => $producerReceipt->id,
            ]),
            'reprint_url' => in_array($producerReceipt->status?->value, ['obsolete', 'cancelled'], true) ? null : route('delivery.projects.receipt-reprint', [
                'tenant' => $tenant->slug, 'project' => $producerReceipt->sales_project_id,
                'receipt' => $producerReceipt->id, 'preview' => 1,
            ]),
        ]);

        $traceSummary = (clone $distributionQuery)
            ->selectRaw('COUNT(*) AS distributions_count')
            ->selectRaw('COUNT(DISTINCT associate_id) AS producers_count')
            ->selectRaw('COUNT(DISTINCT product_id) AS products_count')
            ->selectRaw('COUNT(DISTINCT customer_id) AS recipient_units_count')
            ->selectRaw('COUNT(DISTINCT associate_receipt_id) AS source_receipts_count')
            ->first();
        $consolidatedLines = collect($receipt->documentLines())->map(fn (array $line): array => [
            'project' => (string) ($line['project'] ?? $receipt->project?->title ?? ''),
            'product' => (string) ($line['product'] ?? 'Produto não identificado'),
            'unit' => (string) ($line['unit'] ?? 'un'),
            'quantity' => (string) ($line['quantity'] ?? '0'),
            'unit_price' => (string) ($line['unit_price'] ?? '0'),
            'raw_amount' => (string) ($line['raw_amount'] ?? '0'),
            'document_amount' => (string) ($line['document_amount'] ?? '0.00'),
        ])->values();

        return $this->privateJson([
            'process' => [
                'id' => $receipt->id,
                'number' => $receipt->formatted_number,
                'issued_at' => $receipt->issued_at?->format('d/m/Y'),
                'period' => $this->periodLabel($receipt),
                'project' => $receipt->project ? [
                    'id' => $receipt->project->id,
                    'title' => $receipt->project->title,
                    'code' => $receipt->project->code,
                    'type' => $receipt->project->type,
                ] : null,
                'recipient' => [
                    'type' => $receipt->organization_id ? 'organization' : 'customer',
                    'name' => $receipt->recipient_name,
                ],
                'state' => $state,
                'edit_url' => $receipt->status === CustomerReceiptStatus::DRAFT
                    && $request->user()->can('update_customer::billing::receipt')
                    ? route('accounting.billings.edit', ['tenant' => $tenant->slug, 'receipt' => $receipt->id])
                    : null,
                'reopen_url' => $receipt->status !== CustomerReceiptStatus::DRAFT
                    && in_array($authorization['state'], ['correction_requested', 'invalidated', 'cancelled'], true)
                    && $request->user()->can('update_customer::billing::receipt')
                    ? route('accounting.billings.reopen', ['tenant' => $tenant->slug, 'receipt' => $receipt->id])
                    : null,
                'pdf_url' => route('accounting.fiscal.billing-sheet', [
                    'tenant' => $tenant->slug,
                    'receipt' => $receipt->id,
                ]),
                'workflow' => [
                    'authorization' => $authorization,
                    'fiscal' => $this->fiscalPayload(
                        $fiscal,
                        $tenant->slug,
                        $receipt->id,
                        $receipt->sales_project_id,
                        $request->user()->can('prepare_accounting_fiscal'),
                        $request->user()->can('view_accounting_fiscal_settings'),
                    ),
                    'accountability' => ['state' => 'not_started', 'label' => 'Não iniciado'],
                ],
                'financial' => [
                    'gross' => (float) $receipt->total_gross,
                    'fees' => (float) $receipt->total_fees,
                    'net' => (float) $receipt->total_net,
                    'received' => (float) $receipt->amount_paid,
                    'remaining' => $receipt->remaining_amount,
                    'status' => $receipt->status?->value,
                    'status_label' => $receipt->status?->getLabel() ?? 'Rascunho',
                ],
                'integrity' => $integrityResult,
                'summary' => [
                    'distributions' => (int) ($traceSummary?->distributions_count ?? 0),
                    'producers' => (int) ($traceSummary?->producers_count ?? 0),
                    'products' => (int) ($traceSummary?->products_count ?? 0),
                    'recipient_units' => (int) ($traceSummary?->recipient_units_count ?? 0),
                    'source_receipts' => (int) ($traceSummary?->source_receipts_count ?? 0),
                ],
            ],
            'consolidated_lines' => $consolidatedLines,
            'distributions' => $distributions,
            'payments' => $receipt->payments()
                ->with('bankAccount:id,tenant_id,name')
                ->latest('payment_date')
                ->get(['id', 'tenant_id', 'customer_billing_receipt_id', 'amount', 'payment_date', 'payment_method', 'bank_account_id', 'document_number'])
                ->map(fn ($payment) => [
                    'id' => $payment->id,
                    'date' => $payment->payment_date?->format('d/m/Y'),
                    'amount' => (float) $payment->amount,
                    'method' => $payment->payment_method,
                    'account' => $payment->bankAccount?->name,
                    'document' => $payment->document_number,
                ]),
            'producer_receipts' => $producerReceipts,
            'documents' => $this->documents($receipt, $tenant->id),
            'timeline' => $this->timeline($receipt, $tenant->id, $identities),
            'authorizations' => $receipt->authorizationRounds->map(fn (BillingAuthorization $round) => [
                'id' => $round->id,
                'sequence' => $round->sequence,
                'status' => $round->status->value,
                'label' => $round->status->label(),
                'sent_at' => $round->sent_at?->format('d/m/Y H:i'),
                'sent_by' => $round->sent_by_name ?: 'Membro não identificado',
                'responded_at' => $round->responded_at?->format('d/m/Y H:i'),
                'responded_by' => $round->responded_by_name,
                'message' => $round->response_message,
                'invalidation_reason' => $round->invalidation_reason,
                'organization' => data_get($round->snapshot, 'recipient.name'),
                'validity' => $round->is($latestRound) && $round->status === BillingAuthorizationStatus::AUTHORIZED
                    ? ($isCurrentAuthorizationValid ? 'Válida' : 'Não corresponde ao estado atual')
                    : null,
            ])->values(),
        ]);
    }

    public function repairIntegrity(Request $request, DeliveryParentRecoveryService $recovery): JsonResponse
    {
        $tenant = $this->tenant($request);
        $this->authorizeProcesses($request);
        abort_unless($request->user()->can('review_accounting_processes'), 403);
        $receipt = $this->receipt($request, $tenant->id);

        $result = $recovery->restoreForCustomerReceipt($receipt, $request->user());
        $message = $result['restored'] !== []
            ? count($result['restored']).' registro(s) de entrega restaurado(s). A integridade foi verificada novamente.'
            : 'Nenhuma correção automática era necessária.';

        return $this->privateJson([
            'message' => $message,
            'restored' => $result['restored'],
            'unresolved' => $result['unresolved'],
        ]);
    }

    public function sendAuthorization(Request $request, BillingAuthorizationWorkflowService $workflow): JsonResponse
    {
        $tenant = $this->tenant($request);
        $this->authorizeProcesses($request);
        $validated = $request->validate(['operation_key' => ['required', 'uuid']]);
        $receipt = $this->receipt($request, $tenant->id);
        $this->authorize('send', [BillingAuthorization::class, $receipt]);

        try {
            $round = $workflow->send($receipt, $request->user(), $validated['operation_key']);
        } catch (BillingAuthorizationBlockedException $exception) {
            return $this->privateJson(['message' => $exception->getMessage(), 'issues' => $exception->issues], 422);
        }

        return $this->privateJson(['message' => 'Cobrança enviada para a organização.', 'authorization' => $this->authorizationPayload($round)]);
    }

    public function storeAuthorizationAccess(Request $request): JsonResponse
    {
        $tenant = $this->tenant($request);
        $this->authorizeProcesses($request);
        $receipt = $this->receipt($request, $tenant->id);
        $this->authorize('send', [BillingAuthorization::class, $receipt]);
        abort_unless($receipt->organization_id, 422, 'Este processo não está vinculado a uma organização compradora.');
        $data = $request->validate([
            'email' => ['required', 'email:rfc', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
        ]);
        $email = mb_strtolower(trim($data['email']));
        $access = OrganizationAuthorizedEmail::withoutGlobalScopes()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'organization_id' => $receipt->organization_id,
            'email' => $email,
        ], [
            'name' => trim((string) ($data['name'] ?? '')) ?: $receipt->organization?->responsible_name,
            'active' => true,
        ]);
        activity()->performedOn($access)->causedBy($request->user())->withProperties([
            'tenant_id' => $tenant->id,
            'organization_id' => $receipt->organization_id,
            'receipt_id' => $receipt->id,
        ])->log('Acesso de autorização da organização configurado pelo portal contábil');

        $hasAccount = User::query()->where('status', true)->whereRaw('LOWER(email) = ?', [$email])->exists();

        return $this->privateJson([
            'message' => $hasAccount
                ? 'E-mail autorizado. A organização já pode receber e responder à solicitação.'
                : 'E-mail autorizado. O representante deverá entrar com este mesmo e-mail para responder.',
            'access' => $this->authorizationAccessPayload($receipt->fresh('organization'), $request),
        ]);
    }

    public function cancelAuthorization(Request $request, BillingAuthorizationWorkflowService $workflow): JsonResponse
    {
        $tenant = $this->tenant($request);
        $this->authorizeProcesses($request);
        $validated = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']]);
        $round = BillingAuthorization::withoutGlobalScopes()->where('tenant_id', $tenant->id)
            ->where('customer_billing_receipt_id', (int) $request->route('receipt'))
            ->findOrFail((int) $request->route('billingAuthorization'));
        $this->authorize('cancel', $round);
        $round = $workflow->cancel($round, $request->user(), $validated['reason']);

        return $this->privateJson(['message' => 'Rodada cancelada.', 'authorization' => $this->authorizationPayload($round)]);
    }

    private function processQuery(int $tenantId): Builder
    {
        return CustomerBillingReceipt::query()
            ->where('tenant_id', $tenantId)
            ->select([
                'id', 'tenant_id', 'sales_project_id', 'customer_id', 'organization_id',
                'receipt_year', 'receipt_number', 'receipt_label', 'tenant_receipt_year',
                'tenant_receipt_number', 'project_receipt_year', 'project_receipt_number',
                'issued_at', 'from_date', 'to_date', 'status', 'total_gross', 'total_fees',
                'total_net', 'amount_paid', 'delivery_ids', 'created_at',
            ])
            ->with([
                'project:id,tenant_id,title,code,type,status,receipt_numbering_scope,receipt_number_format,receipt_project_reference',
                'customer:id,tenant_id,name,trade_name,organization_id',
                'organization:id,tenant_id,name',
                'latestAuthorizationRound',
            ])
            ->withCount('billingDistributions')
            ->withCount([
                'billingDistributions as invalid_distributions_count' => fn (Builder $query) => $this->invalidDistributionQuery($query),
                'billingDistributions as structural_distributions_count' => fn (Builder $query) => $query->whereNull('parent_delivery_id'),
                'billingDistributions as incomplete_distributions_count' => fn (Builder $query) => $this->incompleteDistributionQuery($query),
            ]);
    }

    private function invalidDistributionQuery(Builder $query): Builder
    {
        return $query->where(function (Builder $invalid): void {
            $invalid->whereNull('parent_delivery_id')
                ->orWhereNull('customer_id')
                ->orWhere('quantity', '<=', 0)
                ->orWhere('unit_price', '<=', 0)
                ->orWhere('status', '!=', DeliveryStatus::APPROVED->value);
        });
    }

    private function incompleteDistributionQuery(Builder $query): Builder
    {
        return $query->where(function (Builder $incomplete): void {
            $incomplete->whereNull('customer_id')
                ->orWhere('quantity', '<=', 0)
                ->orWhere('unit_price', '<=', 0)
                ->orWhere('status', '!=', DeliveryStatus::APPROVED->value);
        });
    }

    private function criticalProcessQuery(Builder $query): Builder
    {
        return $query->where(function (Builder $critical): void {
            $critical->whereNull('sales_project_id')
                ->orWhere(function (Builder $recipient): void {
                    $recipient->whereNull('customer_id')->whereNull('organization_id');
                })
                ->orWhere(function (Builder $recipient): void {
                    $recipient->whereNotNull('customer_id')->whereNotNull('organization_id');
                })
                ->orWhereHas('billingDistributions', fn (Builder $distribution) => $distribution->whereNull('parent_delivery_id'))
                ->orWhere(function (Builder $closed): void {
                    $closed->where('status', '!=', CustomerReceiptStatus::DRAFT->value)
                        ->where(function (Builder $invalid): void {
                            $invalid->whereDoesntHave('billingDistributions')
                                ->orWhereHas('billingDistributions', fn (Builder $distribution) => $this->invalidDistributionQuery($distribution))
                                ->orWhere('total_net', '<=', 0);
                        });
                });
        });
    }

    private function draftPreparationQuery(Builder $query): Builder
    {
        return $query
            ->where('status', CustomerReceiptStatus::DRAFT->value)
            ->whereNotNull('sales_project_id')
            ->where(function (Builder $recipient): void {
                $recipient->where(function (Builder $customer): void {
                    $customer->whereNotNull('customer_id')->whereNull('organization_id');
                })->orWhere(function (Builder $organization): void {
                    $organization->whereNull('customer_id')->whereNotNull('organization_id');
                });
            })
            ->whereDoesntHave('billingDistributions', fn (Builder $distribution) => $distribution->whereNull('parent_delivery_id'));
    }

    private function structurallyValidQuery(Builder $query): Builder
    {
        return $query
            ->whereNotNull('sales_project_id')
            ->where(function (Builder $recipient): void {
                $recipient->where(function (Builder $customer): void {
                    $customer->whereNotNull('customer_id')->whereNull('organization_id');
                })->orWhere(function (Builder $organization): void {
                    $organization->whereNull('customer_id')->whereNotNull('organization_id');
                });
            })
            ->whereHas('billingDistributions')
            ->whereDoesntHave('billingDistributions', fn (Builder $distribution) => $this->invalidDistributionQuery($distribution))
            ->where(function (Builder $snapshot): void {
                $snapshot->where('status', CustomerReceiptStatus::DRAFT->value)
                    ->orWhere('total_net', '>', 0);
            });
    }

    private function processRow(
        CustomerBillingReceipt $receipt,
        AccountingNextActionResolver $resolver,
        FiscalGateService $fiscalGate,
        string $tenantSlug,
    ): array {
        $isDraft = $receipt->status === CustomerReceiptStatus::DRAFT;
        $distributionCount = (int) $receipt->billing_distributions_count;
        $structuralCount = (int) $receipt->structural_distributions_count;
        $incompleteCount = (int) $receipt->incomplete_distributions_count;
        if ($isDraft) {
            $draftIds = collect($receipt->delivery_ids)->map(fn ($id): int => (int) $id)->filter()->unique()->values();
            if ($draftIds->isNotEmpty()) {
                $draftRows = ProductionDelivery::withoutGlobalScopes()->where('tenant_id', $receipt->tenant_id)
                    ->whereIn('id', $draftIds)->get(['id', 'parent_delivery_id', 'customer_id', 'quantity', 'unit_price', 'status']);
                $distributionCount = $draftRows->count();
                $structuralCount = $draftRows->whereNull('parent_delivery_id')->count() + max(0, $draftIds->count() - $distributionCount);
                $incompleteCount = $draftRows->filter(function (ProductionDelivery $row): bool {
                    $status = $row->status instanceof DeliveryStatus ? $row->status->value : (string) $row->status;

                    return ! $row->customer_id || (float) $row->quantity <= 0 || (float) $row->unit_price <= 0
                        || $status !== DeliveryStatus::APPROVED->value;
                })->count();
            }
        }
        $critical = $structuralCount;
        $critical += ! $receipt->sales_project_id ? 1 : 0;
        $critical += (($receipt->customer_id && $receipt->organization_id) || (! $receipt->customer_id && ! $receipt->organization_id)) ? 1 : 0;
        $critical += ! $isDraft && $distributionCount < 1 ? 1 : 0;
        $critical += ! $isDraft ? $incompleteCount : 0;
        $critical += ! $isDraft && (float) $receipt->total_net <= 0 ? 1 : 0;
        $preparation = $isDraft
            ? $incompleteCount + ($distributionCount < 1 ? 1 : 0)
            : 0;
        $authorization = $this->authorizationPayload($receipt->latestAuthorizationRound);
        $fiscal = ! $isDraft ? $fiscalGate->evaluate($receipt, (int) $receipt->tenant_id) : null;
        $state = $resolver->resolve($receipt->status, $critical, $authorization['state'], $fiscal, $preparation);

        return [
            'id' => $receipt->id,
            'number' => $receipt->formatted_number,
            'issued_at' => $receipt->issued_at?->format('d/m/Y'),
            'period' => $this->periodLabel($receipt),
            'project' => $receipt->project?->title ?? 'Projeto não identificado',
            'project_code' => $receipt->project?->code,
            'recipient' => $receipt->recipient_name,
            'recipient_type' => $receipt->organization_id ? 'Organização' : 'Cliente',
            'gross' => (float) $receipt->total_gross,
            'fees' => (float) $receipt->total_fees,
            'net' => (float) $receipt->total_net,
            'received' => (float) $receipt->amount_paid,
            'remaining' => $receipt->remaining_amount,
            'distributions' => $distributionCount,
            'critical_issues' => $critical,
            'preparation_issues' => $preparation,
            'state' => $state,
            'authorization' => $authorization,
            'fiscal' => $this->fiscalPayload($fiscal, $tenantSlug, $receipt->id),
            'accountability' => ['state' => 'not_started', 'label' => 'Não iniciado'],
            'url' => route('accounting.processes.show', ['tenant' => $tenantSlug, 'receipt' => $receipt->id]),
        ];
    }

    private function fiscalPayload(
        ?array $gate,
        string $tenantSlug,
        int $receiptId,
        ?int $projectId = null,
        bool $canPrepare = false,
        bool $canViewSettings = false,
    ): array {
        if ($gate === null) {
            return ['state' => 'not_started', 'label' => 'Aguardando autorização', 'ready' => false, 'blocks' => []];
        }

        return [
            'state' => $gate['status'],
            'label' => $gate['ready'] ? 'Pronto para emissão' : 'Bloqueado',
            'ready' => $gate['ready'],
            'document_type' => $gate['document_type_label'],
            'expected_amount' => $gate['expected_fiscal_amount'] !== null ? (float) $gate['expected_fiscal_amount'] : null,
            'blocks' => $gate['blocks'],
            'billing_sheet_url' => $canPrepare ? route('accounting.fiscal.billing-sheet', ['tenant' => $tenantSlug, 'receipt' => $receiptId]) : null,
            'fiscal_summary_url' => $canPrepare && $gate['ready'] ? route('accounting.fiscal.show', ['tenant' => $tenantSlug, 'receipt' => $receiptId]) : null,
            'prepare_url' => $canPrepare ? route('accounting.fiscal.prepare', ['tenant' => $tenantSlug, 'receipt' => $receiptId]) : null,
            'settings_url' => $canViewSettings ? route('accounting.fiscal.settings', array_filter([
                'tenant' => $tenantSlug, 'receipt' => $receiptId, 'project' => $projectId,
            ], fn ($value) => $value !== null)) : null,
        ];
    }

    private function filterOptions(int $tenantId, User $user): array
    {
        $billingQuery = fn () => app(AccountingAccessService::class)->scopeBillings(
            CustomerBillingReceipt::query()->where('tenant_id', $tenantId), $user, $tenantId,
        );
        $projectIds = $billingQuery()
            ->whereNotNull('sales_project_id')->distinct()->pluck('sales_project_id');
        $organizationIds = $billingQuery()
            ->whereNotNull('organization_id')->distinct()->pluck('organization_id');
        $customerIds = $billingQuery()
            ->whereNotNull('customer_id')->distinct()->pluck('customer_id');

        return [
            'projects' => SalesProject::query()->where('tenant_id', $tenantId)->whereIn('id', $projectIds)
                ->orderByDesc('created_at')->get(['id', 'title', 'code'])->map(fn (SalesProject $project) => [
                    'id' => $project->id,
                    'label' => trim(($project->code ? $project->code.' · ' : '').$project->title),
                ]),
            'organizations' => Organization::query()->where('tenant_id', $tenantId)->whereIn('id', $organizationIds)
                ->orderBy('name')->get(['id', 'name'])->map(fn (Organization $organization) => [
                    'id' => $organization->id,
                    'label' => $organization->name,
                ]),
            'customers' => Customer::query()->where('tenant_id', $tenantId)->whereIn('id', $customerIds)
                ->orderBy('name')->get(['id', 'name', 'trade_name'])->map(fn (Customer $customer) => [
                    'id' => $customer->id,
                    'label' => $customer->trade_name ?: $customer->name,
                ]),
            'financial_statuses' => collect(CustomerReceiptStatus::cases())->map(fn (CustomerReceiptStatus $status) => [
                'value' => $status->value,
                'label' => $status->getLabel(),
            ]),
        ];
    }

    private function documents(CustomerBillingReceipt $receipt, int $tenantId): array
    {
        return Document::query()
            ->where('tenant_id', $tenantId)
            ->where('documentable_type', CustomerBillingReceipt::class)
            ->where('documentable_id', $receipt->id)
            ->latest('document_date')
            ->limit(30)
            ->get(['id', 'name', 'original_name', 'category', 'mime_type', 'size', 'document_date', 'created_at'])
            ->map(fn (Document $document) => [
                'id' => $document->id,
                'name' => $document->name ?: $document->original_name,
                'category' => $document->category?->label() ?? 'Documento',
                'mime_type' => $document->mime_type,
                'size' => $document->formatted_size,
                'date' => ($document->document_date ?? $document->created_at)?->format('d/m/Y'),
            ])->all();
    }

    private function timeline(CustomerBillingReceipt $receipt, int $tenantId, TenantIdentityService $identities): array
    {
        if (! Schema::hasTable('activity_log')) {
            return [];
        }

        $query = DB::table('activity_log')
            ->where('subject_type', CustomerBillingReceipt::class)
            ->where('subject_id', $receipt->id);
        if (Schema::hasColumn('activity_log', 'tenant_id')) {
            $query->where('tenant_id', $tenantId);
        } else {
            $query->where('properties->tenant_id', $tenantId);
        }
        $events = $query->latest('created_at')->limit(30)->get([
            'id', 'description', 'event', 'causer_id', 'created_at',
        ]);
        $names = $identities->namesForUsers($tenantId, $events->pluck('causer_id')->filter());

        return $events->map(fn ($event) => [
            'id' => $event->id,
            'description' => $event->description,
            'event' => $event->event,
            'actor' => $names[$event->causer_id] ?? 'Membro não identificado',
            'date' => $event->created_at ? Carbon::parse($event->created_at)->format('d/m/Y H:i') : null,
        ])->all();
    }

    private function receipt(Request $request, int $tenantId): CustomerBillingReceipt
    {
        $id = (int) $request->route('receipt');
        abort_if($id < 1, 404);

        return app(AccountingAccessService::class)->scopeBillings(
            CustomerBillingReceipt::withoutGlobalScopes()->where('tenant_id', $tenantId),
            $request->user(),
            $tenantId,
        )->findOrFail($id);
    }

    private function tenant(Request $request): Tenant
    {
        $tenant = $request->route('tenant');
        abort_unless($tenant instanceof Tenant, 404);
        abort_unless((int) session('tenant_id') === (int) $tenant->id, 403);

        return $tenant;
    }

    private function authorizePortal(Request $request): void
    {
        abort_unless($request->user()?->can('view_accounting_portal'), 403);
    }

    private function authorizeProcesses(Request $request): void
    {
        abort_unless($request->user()?->can('view_accounting_processes'), 403);
    }

    private function sourceReceiptRow(AssociateReceipt $receipt, string $tenantSlug, $names): array
    {
        return [
            'id' => $receipt->id,
            'number' => $receipt->formatted_number,
            'member' => $names[$receipt->associate?->user_id]
                ?? $receipt->associate?->nickname
                ?? 'Membro não identificado',
            'project' => $receipt->project?->title ?? 'Projeto não identificado',
            'project_code' => $receipt->project?->code,
            'issued_at' => $receipt->issued_at?->format('d/m/Y'),
            'status' => $receipt->status?->value ?? 'draft',
            'status_label' => $receipt->status?->getLabel() ?? 'Rascunho',
            'distribution_count' => (int) ($receipt->distributions_count ?? $receipt->distributions()->count()),
            'total_net' => (float) ($receipt->total_net ?? 0),
            'reference_code' => $receipt->verificationIdentity?->reference_code,
            'reprint_url' => in_array($receipt->status?->value, ['obsolete', 'cancelled'], true) ? null : route('delivery.projects.receipt-reprint', [
                'tenant' => $tenantSlug,
                'project' => $receipt->sales_project_id,
                'receipt' => $receipt->id,
                'preview' => 1,
            ]),
            'detail_url' => route('accounting.data.source-receipts.show', [
                'tenant' => $tenantSlug,
                'associateReceipt' => $receipt->id,
            ]),
        ];
    }

    private function authorizationAccessPayload(CustomerBillingReceipt $receipt, Request $request): array
    {
        if (! $receipt->organization_id) {
            return [
                'applicable' => false,
                'configured' => false,
                'organization_email' => null,
                'recipients' => [],
                'buyer_url' => null,
            ];
        }

        $organization = $receipt->relationLoaded('organization')
            ? $receipt->organization
            : Organization::withoutGlobalScopes()->where('tenant_id', $receipt->tenant_id)->find($receipt->organization_id);
        $accesses = OrganizationAuthorizedEmail::withoutGlobalScopes()
            ->where('tenant_id', $receipt->tenant_id)
            ->where('organization_id', $receipt->organization_id)
            ->where('active', true)->orderBy('email')->get(['id', 'email', 'name', 'last_login_at']);
        $userEmail = mb_strtolower(trim((string) $request->user()?->email));
        $canPreview = $request->user()?->isTenantAdmin((int) $receipt->tenant_id)
            || $accesses->contains(fn (OrganizationAuthorizedEmail $access): bool => mb_strtolower(trim($access->email)) === $userEmail);
        $round = $receipt->latestAuthorizationRound;

        return [
            'applicable' => true,
            'configured' => $accesses->isNotEmpty(),
            'organization_email' => $organization?->email,
            'organization_contact' => $organization?->responsible_name,
            'recipients' => $accesses->map(fn (OrganizationAuthorizedEmail $access): array => [
                'email' => $access->email,
                'name' => $access->name,
                'has_account' => User::query()->where('status', true)
                    ->whereRaw('LOWER(email) = ?', [mb_strtolower(trim($access->email))])->exists(),
                'last_access_at' => $access->last_login_at?->format('d/m/Y H:i'),
            ])->values(),
            'buyer_url' => $canPreview && $round ? route('buyer.authorizations.show', [
                'tenant' => $request->route('tenant'),
                'billingAuthorization' => $round->id,
            ]) : null,
        ];
    }

    /** IDs de comprovantes realmente usados por algum faturamento do tenant. */
    private function billingSourceReceiptIds(int $tenantId, User $user): Collection
    {
        $accessibleBillingIds = app(AccountingAccessService::class)->scopeBillings(
            CustomerBillingReceipt::withoutGlobalScopes()->where('tenant_id', $tenantId), $user, $tenantId,
        )->pluck('id');
        $linked = ProductionDelivery::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereIn('billing_receipt_id', $accessibleBillingIds)
            ->whereNotNull('associate_receipt_id')
            ->distinct()->pluck('associate_receipt_id');

        $draftDeliveryIds = app(AccountingAccessService::class)->scopeBillings(
            CustomerBillingReceipt::withoutGlobalScopes()->where('tenant_id', $tenantId), $user, $tenantId,
        )
            ->where('status', CustomerReceiptStatus::DRAFT->value)
            ->get(['delivery_ids'])
            ->flatMap(fn (CustomerBillingReceipt $receipt) => (array) $receipt->delivery_ids)
            ->map(fn ($id): int => (int) $id)->filter()->unique();

        if ($draftDeliveryIds->isNotEmpty()) {
            $linked = $linked->merge(ProductionDelivery::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->whereIn('id', $draftDeliveryIds)
                ->whereNotNull('associate_receipt_id')
                ->pluck('associate_receipt_id'));
        }

        $linked = $linked->merge(app(AccountingAccessService::class)->associateReceiptIds($user, $tenantId));

        return $linked->map(fn ($id): int => (int) $id)->unique()->values();
    }

    private function privateJson(mixed $data, int $status = 200): JsonResponse
    {
        return response()->json($data, $status, [
            'Cache-Control' => 'no-store, private',
            'Pragma' => 'no-cache',
        ]);
    }

    private function queueItem(string $key, string $label, int $count, string $icon, string $tone, array $filters): array
    {
        return compact('key', 'label', 'count', 'icon', 'tone', 'filters');
    }

    private function periodLabel(CustomerBillingReceipt $receipt): string
    {
        if ($receipt->from_date && $receipt->to_date) {
            return $receipt->from_date->format('d/m/Y').' a '.$receipt->to_date->format('d/m/Y');
        }

        return $receipt->issued_at?->format('m/Y') ?? 'Sem período';
    }

    private function authorizationPayload(?BillingAuthorization $authorization, ?bool $isCurrentlyValid = null): array
    {
        if (! $authorization) {
            return ['state' => 'legacy_unsubmitted', 'label' => 'Processo anterior ao workflow', 'sequence' => null];
        }

        $state = $authorization->status->value;
        $label = $authorization->status->label();
        if ($authorization->status === BillingAuthorizationStatus::AUTHORIZED && $isCurrentlyValid === false) {
            $state = BillingAuthorizationStatus::INVALIDATED->value;
            $label = 'Autorização não corresponde ao estado atual';
        }

        return [
            'state' => $state,
            'label' => $label,
            'sequence' => $authorization->sequence,
            'sent_at' => $authorization->sent_at?->format('d/m/Y H:i'),
            'responded_at' => $authorization->responded_at?->format('d/m/Y H:i'),
        ];
    }
}
