<?php

namespace App\Http\Controllers\Finance;

use App\Enums\CashMovementType;
use App\Enums\FinancialReceiptStatus;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Controllers\FinancialReceiptController;
use App\Http\Requests\Finance\CancelFinancialReceiptRequest;
use App\Http\Requests\Finance\SaveFinancialReceiptRequest;
use App\Models\BankAccount;
use App\Models\CashMovement;
use App\Models\ChartAccount;
use App\Models\FinancialReceipt;
use App\Models\Tenant;
use App\Services\FinancialReceiptService;
use App\Services\NumberInWordsService;
use App\Services\TenantIdentityService;
use App\Support\FinanceModuleRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class FinancialPortalController extends Controller
{
    public function index(Request $request): View
    {
        $tenant = $this->tenant($request);
        $this->authorize('viewAny', FinancialReceipt::class);
        $movementTotals = CashMovement::query()
            ->where('tenant_id', $tenant->id)
            ->whereBetween('movement_date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
            ->whereIn('type', [CashMovementType::INCOME->value, CashMovementType::EXPENSE->value])
            ->selectRaw('type, SUM(amount) as total')->groupBy('type')->pluck('total', 'type');
        $accounts = BankAccount::query()->where('tenant_id', $tenant->id)->active()
            ->orderByDesc('is_default')->orderBy('name')->get(['id', 'name', 'type', 'current_balance', 'is_default']);

        return view('finance.dashboard', [
            'tenant' => $tenant,
            'accounts' => $accounts,
            'recentReceipts' => FinancialReceipt::query()->where('tenant_id', $tenant->id)->with('bankAccount:id,name')
                ->latest('received_on')->latest('id')->limit(6)->get(),
            'recentMovements' => CashMovement::query()->where('tenant_id', $tenant->id)->with('bankAccount:id,name')
                ->latest('movement_date')->latest('id')->limit(8)
                ->get(['id', 'tenant_id', 'type', 'amount', 'description', 'movement_date', 'bank_account_id', 'document_number']),
            'summary' => [
                'balance' => (float) $accounts->sum('current_balance'),
                'income' => (float) ($movementTotals[CashMovementType::INCOME->value] ?? 0),
                'expense' => (float) ($movementTotals[CashMovementType::EXPENSE->value] ?? 0),
                'drafts' => FinancialReceipt::query()->where('tenant_id', $tenant->id)->where('status', FinancialReceiptStatus::DRAFT->value)->count(),
            ],
            'tools' => $this->tools($request),
        ]);
    }

    public function receipts(Request $request): View
    {
        $tenant = $this->tenant($request);
        $this->authorize('viewAny', FinancialReceipt::class);
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', 'in:draft,issued,cancelled'],
            'from' => ['nullable', 'date'], 'until' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        $receipts = FinancialReceipt::query()->where('tenant_id', $tenant->id)->with('bankAccount:id,name')
            ->when($filters['q'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('payer_name', 'like', '%'.$search.'%')->orWhere('payer_document', 'like', '%'.$search.'%')
                        ->orWhere('payment_reference', 'like', '%'.$search.'%')->orWhere('receipt_number', $search);
                });
            })
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['from'] ?? null, fn ($query, $date) => $query->whereDate('received_on', '>=', $date))
            ->when($filters['until'] ?? null, fn ($query, $date) => $query->whereDate('received_on', '<=', $date))
            ->latest('received_on')->latest('id')->paginate(20)->withQueryString();

        return view('finance.receipts.index', compact('tenant', 'receipts', 'filters'));
    }

    public function create(Request $request): View
    {
        $tenant = $this->tenant($request);
        $this->authorize('create', FinancialReceipt::class);

        return view('finance.receipts.form', $this->formData($tenant));
    }

    public function store(SaveFinancialReceiptRequest $request, FinancialReceiptService $service): RedirectResponse
    {
        $tenant = $this->tenant($request);
        $receipt = $service->createDraft($tenant->id, $request->validated(), $request->user());

        return redirect()->route('finance.receipts.show', ['tenant' => $tenant->slug, 'financialReceipt' => $receipt])
            ->with('success', 'Rascunho salvo. Confira os dados antes de emitir.');
    }

    public function show(Request $request, Tenant $tenant, FinancialReceipt $financialReceipt, TenantIdentityService $identity): View
    {
        $this->assertRouteTenant($request, $tenant);
        $this->authorize('view', $financialReceipt);
        $financialReceipt->load(['items', 'bankAccount:id,name,type', 'chartAccount:id,code,name']);

        return view('finance.receipts.show', ['tenant' => $tenant, 'receipt' => $financialReceipt,
            'receiverName' => $identity->displayName($financialReceipt->tenant_id, $financialReceipt->issued_by)]);
    }

    public function edit(Request $request, Tenant $tenant, FinancialReceipt $financialReceipt): View
    {
        $this->assertRouteTenant($request, $tenant);
        $this->authorize('update', $financialReceipt);
        $financialReceipt->load('items');

        return view('finance.receipts.form', $this->formData($tenant, $financialReceipt));
    }

    public function update(SaveFinancialReceiptRequest $request, Tenant $tenant, FinancialReceipt $financialReceipt, FinancialReceiptService $service): RedirectResponse
    {
        $this->assertRouteTenant($request, $tenant);
        $receipt = $service->updateDraft($financialReceipt, $request->validated(), $request->user());

        return redirect()->route('finance.receipts.show', ['tenant' => $tenant->slug, 'financialReceipt' => $receipt])->with('success', 'Rascunho atualizado.');
    }

    public function issue(Request $request, Tenant $tenant, FinancialReceipt $financialReceipt, FinancialReceiptService $service): RedirectResponse
    {
        $this->assertRouteTenant($request, $tenant);
        $receipt = $service->issue($financialReceipt, $request->user());

        return redirect()->route('finance.receipts.show', ['tenant' => $tenant->slug, 'financialReceipt' => $receipt])
            ->with('success', 'Recibo emitido e entrada registrada no caixa.');
    }

    public function cancel(CancelFinancialReceiptRequest $request, Tenant $tenant, FinancialReceipt $financialReceipt, FinancialReceiptService $service): RedirectResponse
    {
        $this->assertRouteTenant($request, $tenant);
        $receipt = $service->cancel($financialReceipt, $request->user(), $request->validated('reason'));

        return redirect()->route('finance.receipts.show', ['tenant' => $tenant->slug, 'financialReceipt' => $receipt])
            ->with('success', 'Recibo cancelado e lançamento estornado.');
    }

    public function print(Request $request, Tenant $tenant, FinancialReceipt $financialReceipt, FinancialReceiptController $printer): Response
    {
        $this->assertRouteTenant($request, $tenant);

        return $printer->print(
            $financialReceipt,
            app(NumberInWordsService::class),
            app(TenantIdentityService::class),
        );
    }

    private function tenant(Request $request): Tenant
    {
        $tenant = $request->route('tenant');
        abort_unless($tenant instanceof Tenant && (int) session('tenant_id') === (int) $tenant->id, 403);

        return $tenant;
    }

    private function assertRouteTenant(Request $request, Tenant $tenant): void
    {
        abort_unless($this->tenant($request)->is($tenant), 403);
    }

    private function formData(Tenant $tenant, ?FinancialReceipt $receipt = null): array
    {
        return ['tenant' => $tenant, 'receipt' => $receipt,
            'accounts' => BankAccount::query()->where('tenant_id', $tenant->id)->active()->orderByDesc('is_default')->orderBy('name')->get(['id', 'name', 'type', 'current_balance']),
            'chartAccounts' => ChartAccount::query()->where('tenant_id', $tenant->id)->active()->allowsEntries()->orderBy('code')->get(['id', 'code', 'name']),
            'paymentMethods' => PaymentMethod::cases()];
    }

    private function tools(Request $request): array
    {
        $user = $request->user();
        $tenant = $this->tenant($request);

        return collect(FinanceModuleRegistry::all())
            ->filter(fn (array $tool) => $user->can('view_any_'.$tool['permission']))
            ->map(fn (array $tool, string $key): array => [
                'label' => $tool['label'],
                'description' => $tool['writable'] ? 'Consultar, criar e editar' : 'Consulta financeira protegida',
                'icon' => $tool['icon'],
                'url' => route('finance.management.index', ['tenant' => $tenant->slug, 'module' => $key]),
            ])
            ->values()->all();

    }
}
