<?php

namespace App\Http\Controllers\Delivery;

use App\Exports\DeliveryOperationalReportExport;
use App\Http\Controllers\Controller;
use App\Models\SalesProject;
use App\Models\Tenant;
use App\Services\DeliveryReportService;
use App\Services\TemplatedPdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

class DeliveryReportController extends Controller
{
    public function __construct(private readonly DeliveryReportService $reports)
    {
        $this->middleware(['auth', 'any.role:registrador_entregas']);
    }

    public function options(Request $request): JsonResponse
    {
        $project = $this->project($request)->load('tenant');

        return response()->json($this->reports->options($project))
            ->header('Cache-Control', 'no-store, private');
    }

    public function updatePreferences(Request $request): JsonResponse
    {
        $project = $this->project($request);
        $validated = $request->validate([
            'type' => ['required', Rule::in(DeliveryReportService::TYPES)],
            'columns' => ['required', 'array', 'min:1', 'max:20'],
            'columns.*' => ['string', Rule::in(array_keys(DeliveryReportService::COLUMNS))],
            'grouping' => ['nullable', Rule::in(['delivery', 'product', 'associate', 'none'])],
            'orientation' => ['required', Rule::in(['portrait', 'landscape'])],
            'table_scale' => ['required', Rule::in([75, 85, 90, 100])],
        ]);

        return response()->json([
            'message' => 'Configuração do relatório salva.',
            'preferences' => $this->reports->updatePreferences($project, $validated['type'], $validated),
        ])->header('Cache-Control', 'no-store, private');
    }

    public function export(Request $request, TemplatedPdfService $pdfService)
    {
        $project = $this->project($request)->load('tenant');
        $validated = $request->validate([
            'type' => ['required', Rule::in(DeliveryReportService::TYPES)],
            'format' => ['required', Rule::in(['pdf', 'xlsx'])],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'associate_ids' => ['nullable', 'array', 'max:1000'],
            'associate_ids.*' => ['integer', 'min:1'],
            'product_ids' => ['nullable', 'array', 'max:1000'],
            'product_ids.*' => ['integer', 'min:1'],
            'customer_ids' => ['nullable', 'array', 'max:1000'],
            'customer_ids.*' => ['integer', 'min:1'],
        ]);
        $report = $this->reports->build($project, $validated);

        abort_if($report['rows']->isEmpty(), 422, 'Nenhum registro corresponde aos filtros selecionados.');

        $slug = match ($validated['type']) {
            'product' => 'entregas-por-produto',
            'customer' => 'distribuicoes-por-cliente',
            default => 'entregas-por-membro',
        };
        $filename = $slug.'-projeto-'.$project->id.'-'.now()->format('Y-m-d');

        if ($validated['format'] === 'xlsx') {
            $response = Excel::download(new DeliveryOperationalReportExport($report), $filename.'.xlsx');
            $response->headers->set('Cache-Control', 'no-store, private');

            return $response;
        }

        $title = match ($validated['type']) {
            'product' => 'Entregas por Produto',
            'customer' => 'Distribuições por Cliente',
            default => 'Entregas por '.$project->tenant->associateTerm(),
        };
        $templateView = match ($validated['type']) {
            'product' => 'pdf.deliveries-by-product',
            'customer' => 'pdf.distributions-by-customer',
            default => 'pdf.deliveries-by-associate',
        };
        $pdf = $pdfService->generateSystemPdf('pdf.delivery-operational-report', $report + [
            'tenant' => $project->tenant,
            'title' => $title,
            'subtitle' => $project->title,
            'generated_at' => now()->format('d/m/Y H:i'),
        ], array_merge(
            $pdfService->systemPdfOptions($templateView, $title, $project->type, (int) $project->tenant_id),
            [
                'paper' => 'a4',
                'orientation' => $report['preferences']['orientation'],
                'prefer_runtime_layout' => true,
                'configuration_view' => $templateView,
            ],
        ));

        return response()->streamDownload(
            fn () => print $pdf->output(),
            $filename.'.pdf',
            ['Content-Type' => 'application/pdf', 'Cache-Control' => 'no-store, private'],
        );
    }

    private function project(Request $request): SalesProject
    {
        $tenantId = (int) session('tenant_id');
        abort_unless($tenantId > 0, 403);

        $tenant = $request->route('tenant');
        if ($tenant instanceof Tenant) {
            abort_unless((int) $tenant->id === $tenantId, 403);
        }

        return SalesProject::query()
            ->where('tenant_id', $tenantId)
            ->findOrFail((int) $request->route('project'));
    }
}
