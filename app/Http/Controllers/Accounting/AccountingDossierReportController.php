<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\CustomerBillingReceipt;
use App\Models\Tenant;
use App\Services\Accounting\AccountingAccessService;
use App\Services\Accounting\BillingDossierReportService;
use App\Services\TemplatedPdfService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AccountingDossierReportController extends Controller
{
    public function export(Request $request, BillingDossierReportService $reports, AccountingAccessService $access)
    {
        $tenant = $request->route('tenant');
        abort_unless($tenant instanceof Tenant && (int) session('tenant_id') === (int) $tenant->id
            && $request->user()?->can('view_accounting_processes'), 403);
        $type = (string) $request->route('reportType');
        abort_unless(isset(BillingDossierReportService::TYPES[$type]), 404);
        $options = $request->validate(['scope' => ['nullable', Rule::in(['billing', 'project_period'])],
            'project' => ['nullable', 'integer', 'min:1'], 'download' => ['nullable', 'boolean']]);
        $billing = $access->scopeBillings(CustomerBillingReceipt::withoutGlobalScopes()->where('tenant_id', $tenant->id),
            $request->user(), $tenant->id)->findOrFail((int) $request->route('receipt'));
        $scope = $type === 'documents' ? 'billing' : ($options['scope'] ?? 'billing');
        if ($scope === 'project_period') {
            $projectIds = ! empty($options['project']) ? [(int) $options['project']] : $billing->projectIds();
            abort_unless(collect($projectIds)->diff($access->projectIds($request->user(), $tenant->id))->isEmpty(), 403,
                'Acesso ao projeto completo é necessário para exportar fora das distribuições desta folha.');
        }
        $report = $reports->capture($billing, $scope, $type !== 'documents' && isset($options['project']) ? (int) $options['project'] : null);
        $definition = BillingDossierReportService::TYPES[$type];
        $report['title'] = $definition['title'];
        $report['report_type'] = $type;
        $pdf = app(TemplatedPdfService::class)->generateSystemPdf($definition['view'], ['report' => $report,
            'title' => $definition['title'], 'tenant' => $report['tenant'], 'project' => $report['project'],
            'generated_at' => $report['captured_at'],
        ], ['title' => $definition['title'], 'report_context' => $report['report_context']]);
        $filename = \Illuminate\Support\Str::slug($definition['title'].'-'.$report['billing']['number']).'.pdf';
        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($request->boolean('download', true) ? 'attachment' : 'inline').'; filename="'.$filename.'"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, private, max-age=0',
            'Pragma' => 'no-cache', 'Expires' => 'Thu, 01 Jan 1970 00:00:00 GMT',
            'X-Content-Type-Options' => 'nosniff',
            'X-SGC-Document-Origin' => 'generated', 'X-SGC-Document-Title' => $definition['title'],
        ]);
    }
}
