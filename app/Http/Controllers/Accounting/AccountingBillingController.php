<?php

namespace App\Http\Controllers\Accounting;

use App\Enums\DeliveryStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\BillingPreviewRequest;
use App\Http\Requests\Accounting\BillingSelectionRequest;
use App\Http\Requests\Accounting\StoreBillingDraftRequest;
use App\Http\Requests\Accounting\StoreBillingPaymentRequest;
use App\Models\BankAccount;
use App\Models\Customer;
use App\Models\CustomerBillingReceipt;
use App\Models\Organization;
use App\Models\ProductionDelivery;
use App\Models\SalesProject;
use App\Models\Tenant;
use App\Services\Accounting\AccountingAccessService;
use App\Services\Accounting\AccountingBillingService;
use App\Services\CustomerBillingReceiptService;
use App\Services\CustomerBillingSelectionService;
use App\Services\TenantIdentityService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountingBillingController extends Controller
{
    public function create(Request $request): View
    {
        $tenant = $this->tenant($request);
        $this->authorize('create', CustomerBillingReceipt::class);
        $validated = $request->validate([
            'project_ids' => ['nullable', 'array', 'max:20'],
            'project_ids.*' => ['integer', 'distinct'],
        ]);
        $this->assertProjectAccess($request, $tenant, $validated['project_ids'] ?? []);

        return view('accounting.billings.editor', ['tenant' => $tenant, 'receipt' => null]);
    }

    public function edit(Request $request): View
    {
        $tenant = $this->tenant($request);
        $receipt = $this->receipt($request, $tenant);
        $this->authorize('update', $receipt);

        return view('accounting.billings.editor', compact('tenant', 'receipt'));
    }

    public function context(Request $request): JsonResponse
    {
        $tenant = $this->tenant($request);
        abort_unless($request->user()?->can('view_accounting_processes'), 403);
        $receipt = $request->route('receipt') ? $this->receipt($request, $tenant) : null;
        $validated = $request->validate([
            'project_ids' => ['nullable', 'array', 'max:20'],
            'project_ids.*' => ['integer', 'distinct'],
        ]);
        $projectIds = collect($validated['project_ids'] ?? $receipt?->projectIds() ?? [])
            ->map(fn ($id): int => (int) $id)->filter()->unique()->values();
        $validProjectIds = app(AccountingAccessService::class)->scopeProjects(
            SalesProject::withoutGlobalScopes()->where('tenant_id', $tenant->id), $request->user(), $tenant->id,
        )
            ->whereIn('id', $projectIds)->pluck('id')->map(fn ($id): int => (int) $id);
        abort_if($validProjectIds->count() !== $projectIds->count(), 422, 'Um dos projetos selecionados não pertence à organização atual.');

        $recipientCustomerIds = collect();
        if ($validProjectIds->isNotEmpty()) {
            $recipientCustomerIds = ProductionDelivery::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->whereNull('deleted_at')
                ->whereIn('sales_project_id', $validProjectIds)
                ->whereNotNull('parent_delivery_id')
                ->whereNotNull('customer_id')
                ->where('status', DeliveryStatus::APPROVED->value)
                ->where('quantity', '>', 0)
                ->where('unit_price', '>', 0)
                ->where(function (Builder $query) use ($receipt): void {
                    $query->whereNull('billing_receipt_id');
                    if ($receipt) {
                        $query->orWhere('billing_receipt_id', $receipt->id);
                    }
                })
                ->distinct()->pluck('customer_id');
        }
        if ($receipt?->customer_id) {
            $recipientCustomerIds->push((int) $receipt->customer_id);
        }
        $customers = Customer::withoutGlobalScopes()->where('tenant_id', $tenant->id)
            ->whereNull('deleted_at')
            ->whereIn('id', $recipientCustomerIds->unique())
            ->where('status', true)->orderBy('name')->get(['id', 'name', 'trade_name', 'organization_id']);
        $organizationIds = $customers->pluck('organization_id')->filter()->map(fn ($id): int => (int) $id)->unique();
        if ($receipt?->organization_id) {
            $organizationIds->push((int) $receipt->organization_id);
        }

        return $this->json([
            'projects' => app(AccountingAccessService::class)->scopeProjects(
                SalesProject::withoutGlobalScopes()->where('tenant_id', $tenant->id), $request->user(), $tenant->id,
            )
                ->orderByDesc('reference_year')->orderBy('title')->get(['id', 'title', 'code', 'type', 'start_date', 'end_date'])
                ->map(fn (SalesProject $item): array => ['id' => $item->id, 'name' => $item->title, 'code' => $item->code, 'type' => $item->type]),
            'customers' => $customers
                ->map(fn (Customer $item): array => ['id' => $item->id, 'name' => $item->trade_name ?: $item->name, 'organization_id' => $item->organization_id]),
            'organizations' => Organization::withoutGlobalScopes()->where('tenant_id', $tenant->id)
                ->whereIn('id', $organizationIds->unique())->where('active', true)
                ->orderBy('name')->get(['id', 'name', 'short_name'])
                ->map(fn (Organization $item): array => ['id' => $item->id, 'name' => $item->short_name ?: $item->name]),
            'bank_accounts' => BankAccount::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('status', true)
                ->orderByDesc('is_default')->orderBy('name')->get(['id', 'name', 'type'])
                ->map(fn (BankAccount $item): array => ['id' => $item->id, 'name' => $item->name, 'type' => $item->type]),
            'draft' => $receipt ? [
                'id' => $receipt->id,
                'project_ids' => $receipt->projectIds(),
                'customer_id' => $receipt->customer_id,
                'organization_id' => $receipt->organization_id,
                'issued_at' => $receipt->issued_at?->format('Y-m-d'),
                'from_date' => $receipt->from_date?->format('Y-m-d'),
                'to_date' => $receipt->to_date?->format('Y-m-d'),
                'notes' => $receipt->notes,
                'distribution_ids' => collect($receipt->delivery_ids)->map(fn ($id): int => (int) $id)->values(),
            ] : null,
            'permissions' => [
                'create' => $request->user()?->can('create_customer::billing::receipt') ?? false,
                'update' => $request->user()?->can('update_customer::billing::receipt') ?? false,
            ],
        ]);
    }

    public function manual(Request $request, CustomerBillingSelectionService $selection, TenantIdentityService $identities): JsonResponse
    {
        $tenant = $this->tenant($request);
        abort_unless($request->user()?->can('view_accounting_processes'), 403);
        $data = $request->validate([
            'project_ids' => ['required', 'array', 'min:1', 'max:20'], 'project_ids.*' => ['integer', 'distinct'],
            'customer_id' => ['nullable', 'integer'], 'organization_id' => ['nullable', 'integer'],
            'from_date' => ['nullable', 'date'], 'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
            'search' => ['nullable', 'string', 'max:100'], 'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $this->assertProjectAccess($request, $tenant, $data['project_ids']);
        abort_if($request->filled('customer_id') === $request->filled('organization_id'), 422, 'Escolha exatamente um destinatário.');
        $receipt = $request->route('receipt') ? $this->receipt($request, $tenant) : null;
        $query = $selection->eligibleQuery($tenant->id, $data['project_ids'], $data['customer_id'] ?? null,
            $data['organization_id'] ?? null, $data['from_date'] ?? null, $data['to_date'] ?? null, $receipt?->id)
            ->with(['product:id,name,unit', 'customer:id,name,trade_name', 'associate:id,user_id,nickname']);
        $query->when(trim((string) ($data['search'] ?? '')) !== '', function (Builder $query) use ($data): void {
            $term = trim((string) $data['search']);
            $query->where(function (Builder $nested) use ($term): void {
                $nested->where('id', ctype_digit($term) ? (int) $term : -1)
                    ->orWhereHas('product', fn (Builder $product) => $product->where('name', 'like', '%'.$term.'%'))
                    ->orWhereHas('associate', fn (Builder $associate) => $associate->where('nickname', 'like', '%'.$term.'%'));
            });
        });
        $page = $query->orderByDesc('delivery_date')->orderByDesc('id')->paginate(25);
        $names = $identities->namesForUsers($tenant->id, $page->getCollection()->pluck('associate.user_id')->filter());
        $page->getCollection()->transform(fn (ProductionDelivery $row): array => [
            'id' => $row->id, 'date' => $row->delivery_date?->format('d/m/Y'),
            'product' => $row->product?->name ?? 'Produto', 'unit' => $row->product?->unit ?: 'un',
            'producer' => $names[$row->associate?->user_id] ?? $row->associate?->nickname ?? 'Produtor',
            'recipient' => $row->customer?->trade_name ?: $row->customer?->name ?: 'Destinatário',
            'quantity' => (string) $row->quantity, 'unit_price' => (string) $row->unit_price,
        ]);

        return $this->json(['distributions' => $page]);
    }

    public function select(BillingSelectionRequest $request, CustomerBillingSelectionService $selection): JsonResponse
    {
        $tenant = $this->tenant($request);
        $data = $request->validated();
        $this->assertProjectAccess($request, $tenant, $data['project_ids']);
        $receipt = $request->route('receipt') ? $this->receipt($request, $tenant) : null;
        $args = [$tenant->id, $data['project_ids'], $data['customer_id'] ?? null, $data['organization_id'] ?? null,
            $data['from_date'] ?? null, $data['to_date'] ?? null, $receipt?->id];
        try {
            $result = match ($data['mode']) {
                'receipts' => (function () use ($selection, $tenant, $data, $args): array {
                    $batches = collect($data['receipt_codes'] ?? [])->map(function (string $code) use ($selection, $tenant, $args): array {
                        $batch = $selection->selectFromAssociateReceiptCodes($tenant->id, [$code], ...array_slice($args, 1));

                        return [
                            'key' => hash('sha256', mb_strtolower(trim($code))), 'label' => trim($code),
                            'selected_ids' => $batch['selected_ids'], 'selected_count' => count($batch['selected_ids']),
                            'excluded_count' => $batch['excluded_count'], 'exclusion_reasons' => $batch['reasons'],
                            'receipt_found' => ($batch['receipt_count'] ?? 0) > 0,
                            'documents' => $batch['documents'] ?? [],
                        ];
                    })->values();
                    $selectedIds = $batches->pluck('selected_ids')->flatten()->map(fn ($id): int => (int) $id)->unique()->values();

                    return [
                        'selected_ids' => $selectedIds->all(),
                        'candidate_count' => $batches->sum(fn (array $batch): int => $batch['selected_count'] + $batch['excluded_count']),
                        'excluded_count' => $batches->sum('excluded_count'),
                        'reasons' => $batches->pluck('exclusion_reasons')->reduce(function (array $all, array $reasons): array {
                            foreach ($reasons as $reason => $count) {
                                $all[$reason] = ($all[$reason] ?? 0) + $count;
                            }

                            return $all;
                        }, []),
                        'batches' => $batches,
                    ];
                })(),
                'manual' => $selection->selectDistributionIds($tenant->id, $data['distribution_ids'] ?? [], ...array_slice($args, 1)),
                default => (function () use ($selection, $args): array {
                    $ids = $selection->eligibleQuery(...$args)->limit(5001)->pluck('id')->all();
                    if (count($ids) > 5000) {
                        throw new \RuntimeException('A seleção ultrapassa 5.000 distribuições. Reduza o período.');
                    }

                    return $selection->selectDistributionIds($args[0], $ids, ...array_slice($args, 1));
                })(),
            };
        } catch (\RuntimeException $exception) {
            return $this->json(['message' => $exception->getMessage()], 422);
        }

        return $this->json($result);
    }

    public function preview(BillingPreviewRequest $request, AccountingBillingService $service): JsonResponse
    {
        $tenant = $this->tenant($request);
        $this->assertProjectAccess($request, $tenant, $request->validated('project_ids'));
        $draft = $request->route('receipt') ? $this->receipt($request, $tenant) : null;
        try {
            return $this->json($service->preview($tenant->id, $request->validated(), $draft));
        } catch (\RuntimeException $exception) {
            return $this->json(['message' => $exception->getMessage()], 422);
        }
    }

    public function store(StoreBillingDraftRequest $request, AccountingBillingService $service): JsonResponse
    {
        $tenant = $this->tenant($request);
        $this->authorize('create', CustomerBillingReceipt::class);
        try {
            $receipt = $service->saveDraft($tenant->id, $request->validated(), $request->user());

            return $this->json(['message' => 'Rascunho salvo.', 'id' => $receipt->id,
                'redirect_url' => route('accounting.billings.edit', [$tenant, $receipt])], 201);
        } catch (\RuntimeException $exception) {
            return $this->json(['message' => $exception->getMessage()], 422);
        }
    }

    public function update(StoreBillingDraftRequest $request, AccountingBillingService $service): JsonResponse
    {
        $tenant = $this->tenant($request);
        $receipt = $this->receipt($request, $tenant);
        $this->authorize('update', $receipt);
        $this->assertProjectAccess($request, $tenant, $request->validated('project_ids'));
        try {
            $saved = $service->saveDraft($tenant->id, $request->validated(), $request->user(), $receipt);

            return $this->json(['message' => 'Alterações salvas.', 'id' => $saved->id]);
        } catch (\RuntimeException $exception) {
            return $this->json(['message' => $exception->getMessage()], 422);
        }
    }

    public function freeze(Request $request, AccountingBillingService $service): JsonResponse
    {
        $tenant = $this->tenant($request);
        $receipt = $this->receipt($request, $tenant);
        $this->authorize('update', $receipt);
        try {
            $frozen = $service->freeze($receipt, $request->user());

            return $this->json(['message' => 'Faturamento conferido e emitido.', 'redirect_url' => route('accounting.processes.show', [$tenant, $frozen])]);
        } catch (\RuntimeException $exception) {
            return $this->json(['message' => $exception->getMessage()], 422);
        }
    }

    public function reopen(Request $request, AccountingBillingService $service): JsonResponse
    {
        $tenant = $this->tenant($request);
        $receipt = $this->receipt($request, $tenant);
        abort_unless($request->user()?->can('update_customer::billing::receipt'), 403);
        try {
            $draft = $service->reopenForCorrection($receipt, $request->user());

            return $this->json(['message' => 'Faturamento reaberto para correção.', 'redirect_url' => route('accounting.billings.edit', [$tenant, $draft])]);
        } catch (\RuntimeException $exception) {
            return $this->json(['message' => $exception->getMessage()], 422);
        }
    }

    public function payment(StoreBillingPaymentRequest $request, CustomerBillingReceiptService $service): JsonResponse
    {
        $tenant = $this->tenant($request);
        $receipt = $this->receipt($request, $tenant);
        try {
            $service->addPayment($receipt, $request->validated());
            activity()->performedOn($receipt)->causedBy($request->user())->withProperties(['tenant_id' => $tenant->id,
                'amount' => $request->validated('amount'), 'operation_key' => $request->validated('operation_key')])->log('Recebimento registrado no Portal Contábil');

            return $this->json(['message' => 'Recebimento registrado.', 'status' => $receipt->fresh()->status?->value]);
        } catch (\RuntimeException $exception) {
            return $this->json(['message' => $exception->getMessage()], 422);
        }
    }

    private function tenant(Request $request): Tenant
    {
        $tenant = $request->route('tenant');
        abort_unless($tenant instanceof Tenant, 404);
        abort_unless((int) session('tenant_id') === (int) $tenant->id, 403);

        return $tenant;
    }

    private function receipt(Request $request, Tenant $tenant): CustomerBillingReceipt
    {
        $value = $request->route('receipt');
        $id = $value instanceof CustomerBillingReceipt ? $value->id : (int) $value;

        return app(AccountingAccessService::class)->scopeBillings(
            CustomerBillingReceipt::withoutGlobalScopes()->where('tenant_id', $tenant->id), $request->user(), $tenant->id,
        )->findOrFail($id);
    }

    private function assertProjectAccess(Request $request, Tenant $tenant, array $projectIds): void
    {
        $requested = collect($projectIds)->map(fn ($id): int => (int) $id)->filter()->unique();
        $allowed = app(AccountingAccessService::class)->scopeProjects(
            SalesProject::withoutGlobalScopes()->where('tenant_id', $tenant->id), $request->user(), $tenant->id,
        )->whereIn('id', $requested)->count();
        abort_if($allowed !== $requested->count(), 403, 'Você não possui acesso a um dos projetos selecionados.');
    }

    private function json(mixed $data, int $status = 200): JsonResponse
    {
        return response()->json($data, $status, ['Cache-Control' => 'no-store, private', 'Pragma' => 'no-cache']);
    }
}
