<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\AssociateReceipt;
use App\Models\CustomerBillingReceipt;
use App\Models\Tenant;
use App\Enums\ReceiptStatus;
use App\Services\Accounting\AccountingAccessService;
use App\Services\Accounting\BillingAssociateReceiptService;
use App\Services\ReceiptDataBuilder;
use App\Services\ReceiptFeeColumnService;
use App\Services\TemplatedPdfService;
use App\Services\TenantIdentityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountingAssociateReceiptController extends Controller
{
    public function index(Request $request, BillingAssociateReceiptService $service, TenantIdentityService $identities): View
    {
        [$tenant, $billing] = $this->context($request);
        $rows = $service->distributions($billing)->with(['product', 'customer', 'associate', 'salesProject', 'associateReceipt.project'])
            ->orderBy('sales_project_id')->orderBy('associate_id')->orderBy('delivery_date')->orderBy('id')->get();
        $names = $identities->namesForUsers($tenant->id, $rows->pluck('associate.user_id')->filter());
        $groups = $rows->groupBy(fn ($row) => $row->sales_project_id.':'.$row->associate_id);
        $reasons = $rows->mapWithKeys(fn ($row) => [$row->id => $service->unavailableReason($row)]);

        return view('accounting.source-receipts.from-billing', compact('tenant', 'billing', 'groups', 'names', 'reasons'));
    }

    public function store(Request $request, BillingAssociateReceiptService $service): RedirectResponse
    {
        [$tenant, $billing] = $this->context($request);
        $data = $request->validate([
            'distribution_ids' => ['required', 'array', 'min:1', 'max:2000'],
            'distribution_ids.*' => ['required', 'integer', 'distinct', 'min:1'],
            'issued_at' => ['required', 'date'],
        ]);
        try {
            $receipts = $service->create($billing, $data['distribution_ids'], $data['issued_at']);
        } catch (\Illuminate\Database\QueryException $exception) {
            report($exception);

            return back()->withInput()->withErrors(['distribution_ids' => 'Não foi possível concluir a criação. Atualize a seleção e tente novamente.']);
        } catch (\RuntimeException $exception) {
            return back()->withInput()->withErrors(['distribution_ids' => $exception->getMessage()]);
        }

        return back()->with('success', $receipts->count().' comprovante(s) do membro criado(s).')
            ->with('created_member_receipts', $receipts->map(fn ($receipt) => [
                'number' => $receipt->formatted_number,
                'url' => route('accounting.billings.member-receipts.print', ['tenant' => $tenant->slug, 'receipt' => $billing->id, 'associateReceipt' => $receipt->id]),
            ])->all());
    }

    public function print(Request $request, BillingAssociateReceiptService $service)
    {
        [$tenant, $billing] = $this->context($request);
        $receipt = AssociateReceipt::withoutGlobalScopes()->where('tenant_id', $tenant->id)
            ->whereIn('id', $service->distributions($billing)->select('associate_receipt_id'))
            ->with(['project', 'associate.user'])->findOrFail((int) $request->route('associateReceipt'));
        abort_if(in_array($receipt->status, [ReceiptStatus::OBSOLETE, ReceiptStatus::CANCELLED], true), 409, 'Atualize o comprovante antes de imprimir.');
        $rows = $receipt->distributions()->where('tenant_id', $tenant->id)->with(['product', 'customer', 'parentDelivery'])
            ->orderBy('delivery_date')->orderBy('id')->get();
        abort_if($rows->isEmpty(), 409, 'Este comprovante não possui distribuições ativas.');
        $data = ReceiptDataBuilder::fromDeliveries($rows, null, $receipt->project, $receipt->fee_snapshot);
        $fees = app(ReceiptFeeColumnService::class);
        $columns = $fees->sanitize(
            $receipt->project->associate_receipt_columns ?? ReceiptFeeColumnService::DEFAULT_COLUMNS,
            $fees->definitions($receipt->project, 'associate', $receipt->fee_snapshot),
            ReceiptFeeColumnService::STATIC_COLUMNS,
        );
        $pdfService = app(TemplatedPdfService::class);
        $pdf = $pdfService->generateSystemPdf('pdf.project-associate-receipt', array_merge($data, [
            'tenant' => $tenant, 'project' => $receipt->project, 'associate' => $receipt->associate,
            'receipt' => $receipt, 'visible_columns' => $columns,
            'table_scale' => $receipt->project->associate_receipt_table_scale ?: 100,
        ]), $pdfService->systemPdfOptions('pdf.project-associate-receipt', 'Comprovante de Entrega', $receipt->project->type, (int) $tenant->id));

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="comprovante-'.$receipt->id.'.pdf"',
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function context(Request $request): array
    {
        $tenant = $request->route('tenant');
        abort_unless($tenant instanceof Tenant && (int) session('tenant_id') === (int) $tenant->id
            && $request->user()?->can('view_accounting_processes'), 403);
        $this->authorize('create', AssociateReceipt::class);
        $billing = app(AccountingAccessService::class)->scopeBillings(
            CustomerBillingReceipt::withoutGlobalScopes()->where('tenant_id', $tenant->id), $request->user(), $tenant->id,
        )->findOrFail((int) $request->route('receipt'));

        return [$tenant, $billing];
    }
}
