<?php

namespace App\Http\Controllers\Accounting;

use App\Enums\FiscalAmountSource;
use App\Enums\FiscalDocumentType;
use App\Filament\Resources\CustomerBillingReceiptResource;
use App\Http\Controllers\Controller;
use App\Models\CustomerBillingReceipt;
use App\Models\Organization;
use App\Models\ProductionDelivery;
use App\Models\SalesProject;
use App\Models\Tenant;
use App\Services\Accounting\AccountingAccessService;
use App\Services\Accounting\AccountingProcessIntegrityService;
use App\Services\Accounting\FiscalGateService;
use App\Services\Accounting\FiscalProfileService;
use App\Services\TemplatedPdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
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
        $query = app(AccountingAccessService::class)->scopeBillings(
            CustomerBillingReceipt::withoutGlobalScopes()->where('tenant_id', $tenant->id),
            $request->user(),
            $tenant->id,
        )
            ->where('status', '!=', 'draft')
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
            $billingReady = ! collect($result['blocks'])->pluck('code')->intersect(['tenant_mismatch', 'financial_integrity_error'])->isNotEmpty();

            return ['id' => $receipt->id, 'number' => $receipt->formatted_number, 'recipient' => $receipt->recipient_name,
                'project' => $receipt->project?->title ?: 'Projeto não identificado', 'authorized_at' => $result['authorization']?->responded_at?->format('d/m/Y H:i'),
                'amount' => (float) ($result['expected_fiscal_amount'] ?? 0), 'document_type' => $result['document_type_label'] ?: 'Não configurado',
                'gate' => $billingReady ? 'ready' : 'blocked', 'label' => $billingReady ? 'Faturamento pronto' : 'Revisão necessária',
                'action' => $billingReady ? 'Imprimir faturamento completo' : ($result['blocks'][0]['message'] ?? 'Revisar processo'),
                'billing_sheet_url' => $billingReady ? route('accounting.fiscal.billing-sheet', ['tenant' => $tenant->slug, 'receipt' => $receipt->id]) : null,
                'fiscal_summary_url' => $result['ready'] ? route('accounting.fiscal.show', ['tenant' => $tenant->slug, 'receipt' => $receipt->id]) : null,
                'settings_url' => route('accounting.fiscal.settings', array_filter(['tenant' => $tenant->slug,
                    'receipt' => $receipt->id, 'project' => $receipt->sales_project_id])),
                'review_url' => route('accounting.processes.show', ['tenant' => $tenant->slug, 'receipt' => $receipt->id])];
        })->when($validated['document_type'] ?? null, fn ($c, $v) => $c->where('document_type', $v)->values())
            ->when($validated['gate'] ?? null, fn ($c, $v) => $c->where('gate', $v)->values());
        $payload = $paginator->toArray();
        $payload['data'] = $rows->all();
        $accessibleOrganizationIds = app(AccountingAccessService::class)->scopeBillings(
            CustomerBillingReceipt::withoutGlobalScopes()->where('tenant_id', $tenant->id),
            $request->user(),
            $tenant->id,
        )->whereNotNull('organization_id')->distinct()->pluck('organization_id');

        return $this->json(['processes' => $payload, 'filters' => [
            'projects' => app(AccountingAccessService::class)->scopeProjects(
                SalesProject::withoutGlobalScopes()->where('tenant_id', $tenant->id), $request->user(), $tenant->id,
            )->orderByDesc('reference_year')->get(['id', 'title'])->map(fn ($p) => ['id' => $p->id, 'label' => $p->title]),
            'organizations' => Organization::withoutGlobalScopes()->where('tenant_id', $tenant->id)
                ->whereIn('id', $accessibleOrganizationIds)->orderBy('name')->get(['id', 'name'])->map(fn ($o) => ['id' => $o->id, 'label' => $o->name]),
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
            'snapshot' => $result['snapshot']]);
    }

    public function billingSheet(Request $request, AccountingProcessIntegrityService $integrity): Response
    {
        $tenant = $this->tenant($request);
        $receipt = $this->receipt($request, $tenant->id);
        abort_unless(
            $request->user()->can('view_accounting_processes')
                || $request->user()->can('prepare_accounting_fiscal'),
            403,
        );
        $isDraft = $receipt->status?->value === 'draft';
        $inspection = $integrity->inspect($receipt);
        $blockingCount = $isDraft
            ? (int) $inspection['critical_count']
            : (int) $inspection['blocking_count'];
        $blockingIssue = $isDraft
            ? collect($inspection['issues'])->firstWhere('severity', 'critical')
            : ($inspection['issues'][0] ?? null);
        abort_if($blockingCount > 0, 422, $blockingIssue['message'] ?? 'O faturamento possui dados que precisam ser corrigidos.');

        $projects = $receipt->includedProjects();
        $projectIds = $projects->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $deliveryIds = collect($receipt->delivery_ids)->map(fn ($id): int => (int) $id)->filter()->unique();
        if ($deliveryIds->isEmpty()) {
            $deliveryIds = ProductionDelivery::withoutGlobalScopes()->where('tenant_id', $tenant->id)
                ->where('billing_receipt_id', $receipt->id)->pluck('id');
        }
        abort_if($deliveryIds->isEmpty(), 422, 'O faturamento não possui distribuições para imprimir.');
        $distributions = ProductionDelivery::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)->whereNull('deleted_at')->whereIn('sales_project_id', $projectIds)
            ->whereNotNull('parent_delivery_id')->whereIn('id', $deliveryIds)
            ->with(['product', 'customer.priceTable'])->orderBy('delivery_date')->get();
        abort_if($distributions->count() !== $deliveryIds->count(), 422, 'Uma ou mais distribuições da folha não pertencem mais ao faturamento validado.');

        $feeColumns = collect($receipt->documentLines())->flatMap(fn (array $line): array => array_keys((array) ($line['fee_values'] ?? [])))->unique()->values()->all();
        $columns = array_values(array_unique(['unit_price', 'gross', ...$feeColumns, 'net']));
        $organizationReport = $receipt->organization && ! $receipt->customer;
        if ($organizationReport) {
            $view = 'pdf.customer-organization-receipt';
            $data = CustomerBillingReceiptResource::buildOrganizationReportData(
                $distributions, $receipt, $tenant, $receipt->project, $receipt->organization, $projects, $columns,
            );
        } else {
            $view = 'pdf.customer-billing-receipt';
            $data = CustomerBillingReceiptResource::buildCustomerReceiptData(
                $distributions, $receipt, $tenant, $receipt->project, $receipt->customer, $projects, $columns,
            );
        }
        $data['table_scale'] = 100;
        $data['is_draft_preview'] = $isDraft;
        $pdfService = app(TemplatedPdfService::class);
        $pdf = $pdfService->generateSystemPdf($view, $data, $pdfService->systemPdfOptions(
            $view,
            $organizationReport ? 'Faturamento Consolidado de Produtos' : 'Faturamento de Produtos — Cliente',
            $receipt->project?->type,
            (int) $tenant->id,
        ));
        $recipient = Str::slug($receipt->recipient_name ?: 'cliente');
        $filename = 'faturamento-'.str_replace('/', '-', $receipt->formatted_number).'-'.$recipient.'.pdf';
        activity()->performedOn($receipt)->causedBy($request->user())->withProperties([
            'tenant_id' => $tenant->id,
            'download' => $request->boolean('download'),
            'draft_preview' => $isDraft,
        ])->log($isDraft ? 'Prévia do faturamento consultada no Portal Contábil' : 'Folha de faturamento consultada no Portal Contábil');

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($request->boolean('download') ? 'attachment' : 'inline').'; filename="'.$filename.'"',
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
            'X-SGC-Document-Title' => ($isDraft ? 'Prévia do faturamento ' : 'Faturamento ').$receipt->formatted_number,
            'X-SGC-Document-Path' => implode('/', [
                'Comprovantes de faturamento',
                (string) $receipt->receipt_year,
                $receipt->issued_at?->format('m') ?: now()->format('m'),
                $receipt->project?->driveFolderName() ?: 'Sem projeto',
            ]),
            'X-SGC-Document-Origin' => 'generated',
        ]);
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
            'authorization_id' => $result['authorization']?->id, 'authorization_sequence' => $result['authorization']?->sequence,
            'fiscal_profile_id' => $result['profile']->id, 'fiscal_profile_version' => $result['profile']->version])
            ->log('Preparação fiscal iniciada');

        return $this->json(['url' => route('accounting.fiscal.billing-sheet', ['tenant' => $tenant->slug, 'receipt' => $receipt->id])]);
    }

    public function settings(Request $request, FiscalProfileService $profiles): View
    {
        abort_unless($request->user()->can('view_accounting_fiscal_settings'), 403);
        $tenant = $this->tenant($request);
        $receipt = $request->integer('receipt') ? $this->receipt($request, $tenant->id, $request->integer('receipt')) : null;
        $projectId = $request->integer('project') ?: ($receipt?->sales_project_id ?: null);
        $project = $projectId ? app(AccountingAccessService::class)->scopeProjects(
            SalesProject::withoutGlobalScopes()->where('tenant_id', $tenant->id), $request->user(), $tenant->id,
        )->findOrFail($projectId) : null;

        return view('accounting.fiscal.settings', ['tenant' => $tenant, 'profile' => $profiles->latest($tenant->id, $projectId), 'project' => $project,
            'receipt' => $receipt,
            'projects' => app(AccountingAccessService::class)->scopeProjects(
                SalesProject::withoutGlobalScopes()->where('tenant_id', $tenant->id), $request->user(), $tenant->id,
            )->orderByDesc('reference_year')->get(['id', 'title']),
            'documentTypes' => FiscalDocumentType::cases(), 'amountSources' => FiscalAmountSource::cases()]);
    }

    public function storeSettings(Request $request, FiscalProfileService $profiles, FiscalGateService $gate): RedirectResponse
    {
        abort_unless($request->user()->can('manage_accounting_fiscal_settings'), 403);
        $tenant = $this->tenant($request);
        $data = $request->validate(['project_id' => ['nullable', 'integer'], 'receipt_id' => ['nullable', 'integer'], 'document_type' => ['nullable', 'in:nfe,nfse,other'],
            'amount_source' => ['nullable', 'in:authorized_gross,authorized_final'], 'require_issuer_tax_id' => ['nullable', 'boolean'],
            'require_issuer_address' => ['nullable', 'boolean'], 'require_recipient_tax_id' => ['nullable', 'boolean'],
            'require_xml' => ['nullable', 'boolean'], 'require_pdf' => ['nullable', 'boolean'], 'standard_notes' => ['nullable', 'string', 'max:2000'], 'active' => ['nullable', 'boolean']]);
        $project = filled($data['project_id'] ?? null) ? app(AccountingAccessService::class)->scopeProjects(
            SalesProject::withoutGlobalScopes()->where('tenant_id', $tenant->id), $request->user(), $tenant->id,
        )->findOrFail($data['project_id']) : null;
        $receipt = filled($data['receipt_id'] ?? null)
            ? app(AccountingAccessService::class)->scopeBillings(
                CustomerBillingReceipt::withoutGlobalScopes()->where('tenant_id', $tenant->id), $request->user(), $tenant->id,
            )->findOrFail($data['receipt_id'])
            : null;
        abort_if($receipt && $project && ! in_array($project->id, $receipt->projectIds(), true), 422, 'A configuração selecionada não pertence ao faturamento de origem.');
        $profiles->save($tenant->id, $project, $data, $request->user());

        if ($receipt) {
            $result = $gate->evaluate($receipt->fresh(['tenant', 'project', 'organization', 'customer.organization']), $tenant->id);
            if ($result['ready']) {
                return redirect()->route('accounting.fiscal.billing-sheet', ['tenant' => $tenant->slug, 'receipt' => $receipt->id]);
            }

            return redirect()->route('accounting.processes.show', ['tenant' => $tenant->slug, 'receipt' => $receipt->id])
                ->with('warning', $result['blocks'][0]['message'] ?? 'Configuração salva. Confira os dados pendentes do faturamento.');
        }

        return redirect()->route('accounting.fiscal.settings', ['tenant' => $tenant->slug] + ($project ? ['project' => $project->id] : []))
            ->with('success', 'Configuração fiscal salva.');
    }

    private function receipt(Request $request, int $tenantId, ?int $receiptId = null): CustomerBillingReceipt
    {
        return app(AccountingAccessService::class)->scopeBillings(
            CustomerBillingReceipt::withoutGlobalScopes()->where('tenant_id', $tenantId), $request->user(), $tenantId,
        )->with(['tenant', 'project', 'organization', 'customer.organization', 'billingDistributions'])
            ->findOrFail($receiptId ?? (int) $request->route('receipt'));
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
        return response()->json($data, $status)->header('Cache-Control', 'no-store, private');
    }
}
