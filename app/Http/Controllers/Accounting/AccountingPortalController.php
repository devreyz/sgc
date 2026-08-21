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
use App\Models\Organization;
use App\Models\ProductionDelivery;
use App\Models\SalesProject;
use App\Models\Tenant;
use App\Services\Accounting\AccountingNextActionResolver;
use App\Services\Accounting\AccountingProcessIntegrityService;
use App\Services\Accounting\BillingAuthorizationValidityService;
use App\Services\Accounting\BillingAuthorizationWorkflowService;
use App\Services\TenantIdentityService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
        $base = CustomerBillingReceipt::query()->where('tenant_id', $tenant->id);

        $critical = (clone $base)
            ->where(function (Builder $query): void {
                $query->whereNull('sales_project_id')
                    ->orWhere(function (Builder $recipient): void {
                        $recipient->whereNull('customer_id')->whereNull('organization_id');
                    })
                    ->orWhere(function (Builder $recipient): void {
                        $recipient->whereNotNull('customer_id')->whereNotNull('organization_id');
                    })
                    ->orWhereDoesntHave('billingDistributions')
                    ->orWhereHas('billingDistributions', fn (Builder $distribution) => $this->invalidDistributionQuery($distribution))
                    ->orWhere(function (Builder $snapshot): void {
                        $snapshot->whereIn('status', [
                            CustomerReceiptStatus::PENDING_PAYMENT->value,
                            CustomerReceiptStatus::PARTIALLY_PAID->value,
                            CustomerReceiptStatus::PAID->value,
                        ])->where('total_net', '<=', 0);
                    });
            })
            ->count();

        $validBase = $this->structurallyValidQuery(clone $base);
        $drafts = (clone $validBase)->where('status', CustomerReceiptStatus::DRAFT->value)->count();
        $closed = (clone $validBase)->where('status', CustomerReceiptStatus::PENDING_PAYMENT->value)
            ->whereDoesntHave('authorizationRounds')->count();
        $partial = (clone $validBase)->where('status', CustomerReceiptStatus::PARTIALLY_PAID->value)->count();

        $items = collect([
            $this->queueItem('critical', 'Inconsistências críticas', $critical, 'alert-triangle', 'danger', [
                'pending' => 'review_inconsistency',
            ]),
            $this->queueItem('drafts', 'Cobranças em preparação', $drafts, 'file-pen-line', 'neutral', [
                'financial_status' => CustomerReceiptStatus::DRAFT->value,
            ]),
            $this->queueItem('closed', 'Prontas para envio', $closed, 'clipboard-check', 'warning', [
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
            $this->queueItem('authorized', 'Autorizações concluídas', (clone $validBase)
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
                'legacy_state' => 'Processos anteriores ao fluxo contábil',
            ],
            'empty' => $items->isEmpty(),
        ]);
    }

    public function processesData(Request $request, AccountingNextActionResolver $resolver): JsonResponse
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

        $query = $this->processQuery($tenant->id);
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
            $query->where(function (Builder $query): void {
                $query->whereNull('sales_project_id')
                    ->orWhere(function (Builder $recipient): void {
                        $recipient->whereNull('customer_id')->whereNull('organization_id');
                    })
                    ->orWhere(function (Builder $recipient): void {
                        $recipient->whereNotNull('customer_id')->whereNotNull('organization_id');
                    })
                    ->orWhereDoesntHave('billingDistributions')
                    ->orWhereHas('billingDistributions', fn (Builder $distribution) => $this->invalidDistributionQuery($distribution));
            });
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
            fn (CustomerBillingReceipt $receipt) => $this->processRow($receipt, $resolver, $tenant->slug)
        );

        return $this->privateJson([
            'processes' => $processes,
            'filters' => $this->filterOptions($tenant->id),
        ]);
    }

    public function processData(
        Request $request,
        AccountingNextActionResolver $resolver,
        AccountingProcessIntegrityService $integrity,
        BillingAuthorizationValidityService $authorizationValidity,
        TenantIdentityService $identities,
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
            'organization:id,tenant_id,name',
            'bankAccount:id,tenant_id,name,type',
        ]);

        $integrityResult = $integrity->inspect($receipt);
        $receipt->load(['authorizationRounds' => fn ($query) => $query->latest('sequence')->limit(50)]);
        $latestRound = $receipt->authorizationRounds->first();
        $isCurrentAuthorizationValid = $latestRound?->status === BillingAuthorizationStatus::AUTHORIZED
            ? $authorizationValidity->isValid($receipt, $latestRound)
            : null;
        $authorization = $this->authorizationPayload($latestRound, $isCurrentAuthorizationValid);
        $state = $resolver->resolve($receipt->status, $integrityResult['critical_count'], $authorization['state']);
        $distributions = $receipt->billingDistributions()
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

        $producerReceiptIds = $receipt->billingDistributions()
            ->whereNotNull('associate_receipt_id')
            ->distinct()
            ->pluck('associate_receipt_id');
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
        ]);

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
                'workflow' => [
                    'authorization' => $authorization,
                    'fiscal' => ['state' => 'not_started', 'label' => 'Não iniciado'],
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
            ],
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
                'total_net', 'amount_paid', 'created_at',
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

    private function processRow(CustomerBillingReceipt $receipt, AccountingNextActionResolver $resolver, string $tenantSlug): array
    {
        $critical = (int) $receipt->invalid_distributions_count;
        $critical += $receipt->billing_distributions_count < 1 ? 1 : 0;
        $critical += ! $receipt->sales_project_id ? 1 : 0;
        $critical += (($receipt->customer_id && $receipt->organization_id) || (! $receipt->customer_id && ! $receipt->organization_id)) ? 1 : 0;
        $critical += $receipt->status?->isLocked() && (float) $receipt->total_net <= 0 ? 1 : 0;
        $authorization = $this->authorizationPayload($receipt->latestAuthorizationRound);
        $state = $resolver->resolve($receipt->status, $critical, $authorization['state']);

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
            'distributions' => (int) $receipt->billing_distributions_count,
            'critical_issues' => $critical,
            'state' => $state,
            'authorization' => $authorization,
            'fiscal' => ['state' => 'not_started', 'label' => 'Não iniciado'],
            'accountability' => ['state' => 'not_started', 'label' => 'Não iniciado'],
            'url' => route('accounting.processes.show', ['tenant' => $tenantSlug, 'receipt' => $receipt->id]),
        ];
    }

    private function filterOptions(int $tenantId): array
    {
        $projectIds = CustomerBillingReceipt::query()->where('tenant_id', $tenantId)
            ->whereNotNull('sales_project_id')->distinct()->pluck('sales_project_id');
        $organizationIds = CustomerBillingReceipt::query()->where('tenant_id', $tenantId)
            ->whereNotNull('organization_id')->distinct()->pluck('organization_id');
        $customerIds = CustomerBillingReceipt::query()->where('tenant_id', $tenantId)
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

        return CustomerBillingReceipt::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->findOrFail($id);
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
