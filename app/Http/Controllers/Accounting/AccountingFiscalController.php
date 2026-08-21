<?php

namespace App\Http\Controllers\Accounting;

use App\Enums\FiscalAmountSource;
use App\Enums\FiscalDocumentType;
use App\Http\Controllers\Controller;
use App\Models\CustomerBillingReceipt;
use App\Models\Organization;
use App\Models\SalesProject;
use App\Models\Tenant;
use App\Services\Accounting\FiscalGateService;
use App\Services\Accounting\FiscalProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountingFiscalController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('view_accounting_fiscal_queue'), 403);

        return view('accounting.fiscal.index', ['tenant' => $this->tenant($request)]);
    }

    public function data(Request $request, FiscalGateService $gate): JsonResponse
    {
        abort_unless($request->user()->can('view_accounting_fiscal_queue'), 403);
        $tenant = $this->tenant($request);
        $validated = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'project' => ['nullable', 'integer'],
            'organization' => ['nullable', 'integer'], 'document_type' => ['nullable', 'in:nfe,nfse,other'],
            'gate' => ['nullable', 'in:ready,blocked'], 'from' => ['nullable', 'date'], 'until' => ['nullable', 'date', 'after_or_equal:from'],
            'page' => ['nullable', 'integer', 'min:1'], 'per_page' => ['nullable', 'integer', 'min:10', 'max:50']]);
        $query = CustomerBillingReceipt::withoutGlobalScopes()->where('tenant_id', $tenant->id)
            ->whereHas('authorizationRounds', fn ($q) => $q->where('status', 'authorized'))
            ->with(['tenant:id,name,legal_name,cnpj,address,city,state', 'project:id,tenant_id,title,code',
                'organization:id,tenant_id,name,cnpj', 'customer:id,tenant_id,organization_id,name,trade_name,cnpj', 'activeAuthorization'])
            ->when($validated['search'] ?? null, fn ($q, $v) => $q->where(fn ($x) => $x->where('receipt_label', 'like', '%'.$v.'%')->orWhereHas('organization', fn ($o) => $o->where('name', 'like', '%'.$v.'%'))->orWhereHas('customer', fn ($c) => $c->where('name', 'like', '%'.$v.'%'))))
            ->when($validated['project'] ?? null, fn ($q, $v) => $q->where('sales_project_id', $v))
            ->when($validated['organization'] ?? null, fn ($q, $v) => $q->where('organization_id', $v))
            ->when($validated['from'] ?? null, fn ($q, $v) => $q->whereDate('issued_at', '>=', $v))
            ->when($validated['until'] ?? null, fn ($q, $v) => $q->whereDate('issued_at', '<=', $v))->latest('issued_at');
        $paginator = $query->paginate((int) ($validated['per_page'] ?? 25));
        $rows = collect($paginator->items())->map(function (CustomerBillingReceipt $receipt) use ($gate, $tenant): array {
            $result = $gate->evaluate($receipt, $tenant->id);

            return ['id' => $receipt->id, 'number' => $receipt->formatted_number, 'recipient' => $receipt->recipient_name,
                'project' => $receipt->project?->title ?: 'Projeto não identificado', 'authorized_at' => $result['authorization']?->responded_at?->format('d/m/Y H:i'),
                'amount' => (float) ($result['expected_fiscal_amount'] ?? 0), 'document_type' => $result['document_type_label'] ?: 'Não configurado',
                'gate' => $result['status'], 'label' => $result['ready'] ? 'Pronto para emissão' : 'Bloqueado',
                'action' => $result['ready'] ? 'Preparar emissão' : ($result['blocks'][0]['message'] ?? 'Revisar processo'),
                'prepare_url' => route('accounting.fiscal.prepare', ['tenant' => $tenant->slug, 'receipt' => $receipt->id]),
                'review_url' => route('accounting.processes.show', ['tenant' => $tenant->slug, 'receipt' => $receipt->id])];
        })->when($validated['document_type'] ?? null, fn ($c, $v) => $c->where('document_type', $v)->values())
            ->when($validated['gate'] ?? null, fn ($c, $v) => $c->where('gate', $v)->values());
        $payload = $paginator->toArray();
        $payload['data'] = $rows->all();

        return $this->json(['processes' => $payload, 'filters' => [
            'projects' => SalesProject::withoutGlobalScopes()->where('tenant_id', $tenant->id)->orderByDesc('reference_year')->get(['id', 'title'])->map(fn ($p) => ['id' => $p->id, 'label' => $p->title]),
            'organizations' => Organization::withoutGlobalScopes()->where('tenant_id', $tenant->id)->orderBy('name')->get(['id', 'name'])->map(fn ($o) => ['id' => $o->id, 'label' => $o->name]),
        ]]);
    }

    public function show(Request $request, FiscalGateService $gate): View
    {
        abort_unless($request->user()->can('prepare_accounting_fiscal'), 403);
        $tenant = $this->tenant($request);
        $receipt = $this->receipt($request, $tenant->id);
        $result = $gate->evaluate($receipt, $tenant->id);
        abort_unless($result['ready'], 422, $result['blocks'][0]['message'] ?? 'Processo bloqueado.');

        return view('accounting.fiscal.show', ['tenant' => $tenant, 'receipt' => $receipt, 'gate' => $result,
            'snapshot' => $result['authorization']->snapshot]);
    }

    public function prepare(Request $request, FiscalGateService $gate): JsonResponse
    {
        abort_unless($request->user()->can('prepare_accounting_fiscal'), 403);
        $tenant = $this->tenant($request);
        $receipt = $this->receipt($request, $tenant->id);
        $result = $gate->evaluate($receipt, $tenant->id);
        if (! $result['ready']) {
            return $this->json(['message' => 'O processo não está pronto.', 'blocks' => $result['blocks']], 422);
        }
        activity()->performedOn($receipt)->causedBy($request->user())->withProperties(['tenant_id' => $tenant->id,
            'authorization_id' => $result['authorization']->id, 'authorization_sequence' => $result['authorization']->sequence,
            'fiscal_profile_id' => $result['profile']->id, 'fiscal_profile_version' => $result['profile']->version])
            ->log('Preparação fiscal iniciada');

        return $this->json(['url' => route('accounting.fiscal.show', ['tenant' => $tenant->slug, 'receipt' => $receipt->id])]);
    }

    public function settings(Request $request, FiscalProfileService $profiles): View
    {
        abort_unless($request->user()->can('view_accounting_fiscal_settings'), 403);
        $tenant = $this->tenant($request);
        $projectId = $request->integer('project') ?: null;
        $project = $projectId ? SalesProject::withoutGlobalScopes()->where('tenant_id', $tenant->id)->findOrFail($projectId) : null;

        return view('accounting.fiscal.settings', ['tenant' => $tenant, 'profile' => $profiles->latest($tenant->id, $projectId), 'project' => $project,
            'projects' => SalesProject::withoutGlobalScopes()->where('tenant_id', $tenant->id)->orderByDesc('reference_year')->get(['id', 'title']),
            'documentTypes' => FiscalDocumentType::cases(), 'amountSources' => FiscalAmountSource::cases()]);
    }

    public function storeSettings(Request $request, FiscalProfileService $profiles): RedirectResponse
    {
        abort_unless($request->user()->can('manage_accounting_fiscal_settings'), 403);
        $tenant = $this->tenant($request);
        $data = $request->validate(['project_id' => ['nullable', 'integer'], 'document_type' => ['nullable', 'in:nfe,nfse,other'],
            'amount_source' => ['nullable', 'in:authorized_gross,authorized_final'], 'require_issuer_tax_id' => ['nullable', 'boolean'],
            'require_issuer_address' => ['nullable', 'boolean'], 'require_recipient_tax_id' => ['nullable', 'boolean'],
            'require_xml' => ['nullable', 'boolean'], 'require_pdf' => ['nullable', 'boolean'], 'standard_notes' => ['nullable', 'string', 'max:2000'], 'active' => ['nullable', 'boolean']]);
        $project = filled($data['project_id'] ?? null) ? SalesProject::withoutGlobalScopes()->where('tenant_id', $tenant->id)->findOrFail($data['project_id']) : null;
        $profiles->save($tenant->id, $project, $data, $request->user());

        return redirect()->route('accounting.fiscal.settings', ['tenant' => $tenant->slug] + ($project ? ['project' => $project->id] : []))
            ->with('success', 'Configuração fiscal salva.');
    }

    private function receipt(Request $request, int $tenantId): CustomerBillingReceipt
    {
        return CustomerBillingReceipt::withoutGlobalScopes()->where('tenant_id', $tenantId)->with(['tenant', 'project', 'organization', 'customer.organization', 'billingDistributions'])->findOrFail((int) $request->route('receipt'));
    }

    private function tenant(Request $request): Tenant
    {
        $tenant = $request->route('tenant');
        abort_unless($tenant instanceof Tenant, 404);
        abort_unless((int) session('tenant_id') === (int) $tenant->id, 403);

        return $tenant;
    }

    private function json(array $data, int $status = 200): JsonResponse
    {
        return response()->json($data,$status)->header('Cache-Control','no-store, private');
    }
}
