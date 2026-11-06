<?php

namespace App\Services\PreDistributions;

use App\Models\PreDistributionEvent;
use App\Models\PreDistributionSheet;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PdfUpdateFooterService;
use App\Services\TemplatedPdfService;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdfDocument;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class PreDistributionReportService
{
    public function __construct(private PreDistributionAccess $access, private PreDistributionSheetService $sheets, private PreDistributionQueryService $queries) {}

    public function sheetGroups(Tenant $tenant, PreDistributionSheet $sheet, bool $showPrices): Collection
    {
        if ($sheet->status === 'cancelled') {
            return collect();
        }

        $items = $sheet->items()->where('tenant_id', $tenant->id)
            ->whereNull('cancelled_at')->where('reported_quantity', '>', 0)->with([
            'project' => fn ($query) => $query->withTrashed()->where('tenant_id', $tenant->id)->select('id', 'title'),
            'customer' => fn ($query) => $query->withTrashed()->where('tenant_id', $tenant->id)->select('id', 'name', 'trade_name', 'organization_id'),
            'customer.organization' => fn ($query) => $query->withTrashed()->where('tenant_id', $tenant->id)->select('id', 'name'),
            'product' => fn ($query) => $query->withTrashed()->where('tenant_id', $tenant->id)->select('id', 'name'),
        ])->orderBy('id')->get([
            'id', 'sales_project_id', 'customer_id', 'product_id', 'unit', 'reported_quantity', ...($showPrices ? ['reference_price'] : []),
        ]);

        return $items->groupBy([
            'sales_project_id',
            fn ($item) => $item->customer?->organization ? 'organization-'.$item->customer->organization_id : 'customer-'.$item->customer_id,
        ])->flatMap(fn ($organizations) => $organizations->values())->map(function (Collection $group) use ($showPrices) {
            $first = $group->first();
            $customers = $group->mapWithKeys(fn ($item) => [$item->customer_id => $item->customer?->trade_name ?: ($item->customer?->name ?? 'Cliente #'.$item->customer_id)])->sort(SORT_NATURAL | SORT_FLAG_CASE);

            return [
                'project' => $first->project?->title ?? 'Projeto indisponível',
                'organization' => $first->customer?->organization?->name ?? $customers->first(),
                'recipient_label' => $first->customer?->organization ? 'Organização' : 'Cliente',
                'tables' => $customers->chunk(4)->map(fn (Collection $destinations) => $this->sheetTable($group, $destinations, $showPrices)),
            ];
        });
    }

    public function printSheet(Tenant $tenant, PreDistributionSheet $sheet, bool $showPrices = false): DomPdfDocument
    {
        $title = 'Relação de Produtos Entregues';

        return app(TemplatedPdfService::class)->generateSystemPdf('pdf.pre-distribution-sheet', [
            'tenant' => $tenant,
            'sheet' => $sheet,
            'groups' => $this->sheetGroups($tenant, $sheet, $showPrices),
            'showPrices' => $showPrices,
            'title' => $title,
            'subtitle' => $sheet->code.' | '.$sheet->operational_date->format('d/m/Y'),
            'hide_header_email' => true,
            'suppress_generation_timestamp' => true,
            'suppress_internal_footer' => true,
        ], [
            'title' => $title,
            'footer_prefix' => 'PLANILHA OPERACIONAL | '.$sheet->code,
            'dompdf_options' => ['isRemoteEnabled' => false],
        ]);
    }

    private function sheetTable(Collection $items, Collection $customers, bool $showPrices): array
    {
        $total = '0.00';
        $rows = $items->whereIn('customer_id', $customers->keys())
            ->groupBy(fn ($item) => json_encode([$item->product_id, $item->unit, $showPrices ? $item->reference_price : null], JSON_THROW_ON_ERROR))
            ->map(function (Collection $group) use ($showPrices, &$total) {
                $first = $group->first();
                $quantity = '0.0000';
                $byCustomer = [];
                foreach ($group as $item) {
                    $quantity = bcadd($quantity, $item->reported_quantity, 4);
                    $byCustomer[$item->customer_id] = bcadd($byCustomer[$item->customer_id] ?? '0', $item->reported_quantity, 4);
                }
                $price = $showPrices ? $first->reference_price : null;
                $amount = $price === null ? null : bcadd(bcmul($quantity, $price, 8), '0.005', 2);
                if ($amount !== null) {
                    $total = bcadd($total, $amount, 2);
                }

                return ['product' => $first->product?->name ?? 'Produto #'.$first->product_id, 'unit' => $first->unit,
                    'quantity' => $quantity, 'by_customer' => $byCustomer, 'price' => $price, 'amount' => $amount];
            })->sortBy('product', SORT_NATURAL | SORT_FLAG_CASE)->values();

        return [
            'customers' => $customers->map(fn ($name, $id) => ['id' => $id, 'name' => $name])->values(),
            'rows' => $rows,
            'total' => $total,
        ];
    }

    public function create(int $tenantId, User $actor, string $key, array $data): array
    {
        $this->access->assertContext($actor, $tenantId);
        abort_unless($this->access->manager($actor, $tenantId), 403);
        $data = Validator::make($data, ['sheet_ids' => 'required|array|min:1|max:100', 'sheet_ids.*' => 'required|integer|distinct|min:1',
            'include_prices' => 'sometimes|boolean', 'previous_report_id' => 'nullable|integer|min:1', 'filters' => 'sometimes|array',
            'filters.sales_project_id' => 'nullable|integer|min:1', 'filters.customer_id' => 'nullable|integer|min:1',
            'filters.organization_id' => 'nullable|integer|min:1', 'filters.assigned_to' => 'nullable|integer|min:1',
            'filters.date_from' => 'nullable|date_format:Y-m-d', 'filters.date_to' => 'nullable|date_format:Y-m-d'])->validate();
        $written = null;
        try {
            return $this->sheets->operate($tenantId, $actor, 'report', $key, $data, function ($operation) use ($tenantId, $actor, $data, &$written) {
                $ids = $this->queries->selection($tenantId, $actor, $data['sheet_ids']);
                $sources = $this->queries->reportSources($tenantId, $actor, $ids, $data['filters'] ?? []);
                if ($sources->isEmpty()) {
                    throw ValidationException::withMessages(['sheet_ids' => 'Não há linhas ativas para os filtros escolhidos.']);
                }
                $previous = empty($data['previous_report_id']) ? null : DB::table('pre_distribution_reports')->where('tenant_id', $tenantId)->find($data['previous_report_id']);
                if (! empty($data['previous_report_id'])) {
                    abort_unless($previous, 404);
                }
                $series = $previous?->series_id ?? (string) Str::uuid();
                $revision = $previous ? 1 + (int) DB::table('pre_distribution_reports')->where('tenant_id', $tenantId)->where('series_id', $series)->max('revision') : 1;
                $publicId = (string) Str::uuid();
                $issued = now();
                $prices = (bool) ($data['include_prices'] ?? false);
                $groups = [];
                foreach ($sources as $source) {
                    $groupKey = json_encode([$source->sales_project_id, $source->customer_id, $source->product_id, $source->unit, $prices ? $source->reference_price : null]);
                    $groups[$groupKey] ??= ['project_id' => $source->sales_project_id, 'customer_id' => $source->customer_id, 'product_id' => $source->product_id, 'project' => $source->project_name, 'customer' => $source->customer_name, 'product' => $source->product_name,
                        'unit' => $source->unit, 'quantity' => null, 'pending' => 0, 'reference_price' => $prices && $source->reference_price !== null ? bcadd((string) $source->reference_price, '0', 4) : null, 'sources' => []];
                    if ($source->reported_quantity === null) {
                        $groups[$groupKey]['pending']++;
                    } else {
                        $groups[$groupKey]['quantity'] = bcadd($groups[$groupKey]['quantity'] ?? '0', (string) $source->reported_quantity, 4);
                    }
                    $groups[$groupKey]['sources'][] = $source->id;
                }
                $tenant = Tenant::findOrFail($tenantId);
                // Do not permit network fetches or remote logos when rendering operational snapshots.
                $tenant = clone $tenant;
                $tenant->logo = null;
                $snapshot = ['sheet_ids' => $ids, 'sheet_versions' => PreDistributionSheet::withoutGlobalScopes()->where('tenant_id', $tenantId)->whereIn('id', $ids)->pluck('version', 'id')->all(),
                    'filters' => $data['filters'] ?? [], 'include_prices' => $prices, 'sources' => $sources->map(fn ($row) => (array) $row)->all(), 'groups' => array_values($groups),
                    'tenant' => $tenant->only(['id', 'name']), 'issued_at' => $issued->toIso8601String()];
                $snapshot['product_totals'] = collect($snapshot['groups'])->groupBy(fn ($row) => json_encode([$row['project_id'], $row['product_id'], $row['unit']]))
                    ->map(function ($rows) {
                        $total = $rows->first();
                        $total['quantity'] = null;
                        $total['pending'] = 0;
                        foreach ($rows as $row) {
                            if ($row['quantity'] !== null) {
                                $total['quantity'] = bcadd($total['quantity'] ?? '0', $row['quantity'], 4);
                            } $total['pending'] += $row['pending'];
                        }

                        return Arr::only($total, ['project', 'product', 'unit', 'quantity', 'pending']);
                    })->values()->all();
                $path = 'pre-distribution-reports/'.$tenantId.'/'.$publicId.'.pdf';
                $id = DB::table('pre_distribution_reports')->insertGetId(['tenant_id' => $tenantId, 'public_id' => $publicId, 'series_id' => $series,
                    'code' => $publicId, 'revision' => $revision, 'actor_id' => $actor->id, 'operation_id' => $operation->id,
                    'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR), 'path' => $path, 'sha256' => str_repeat('0', 64), 'created_at' => $issued]);
                $code = $previous?->code ?? 'PRE-'.str_pad((string) $id, 6, '0', STR_PAD_LEFT).'/'.$issued->format('Y');
                $html = view('pdf.pre-distributions', ['tenant' => $tenant, 'title' => 'Relação Preliminar de Produtos', 'snapshot' => $snapshot,
                    'code' => $code, 'revision' => $revision, 'hide_header_email' => true, 'suppress_generation_timestamp' => true, 'suppress_internal_pagination' => true, 'suppress_internal_footer' => true])->render();
                $pdf = Pdf::loadHTML($html)->setPaper('a4')->setOption('isRemoteEnabled', false);
                app(PdfUpdateFooterService::class)->apply($pdf, $issued, false, 'PRELIMINAR | '.$code.' | v'.$revision);
                $bytes = $pdf->output();
                $written = $path;
                if (! Storage::disk('local')->put($path, $bytes)) {
                    throw new \RuntimeException('Não foi possível armazenar o PDF preliminar.');
                }
                DB::table('pre_distribution_reports')->where('tenant_id', $tenantId)->where('id', $id)->update(['code' => $code, 'sha256' => hash('sha256', $bytes)]);
                foreach ($snapshot['sheet_versions'] as $sheetId => $version) {
                    PreDistributionEvent::create(['tenant_id' => $tenantId, 'sheet_id' => $sheetId,
                        'actor_id' => $actor->id, 'operation_id' => $operation->id, 'event' => 'report_issued', 'version' => $version,
                        'after' => ['report_id' => $id, 'revision' => $revision, 'sha256' => hash('sha256', $bytes)], 'created_at' => $issued]);
                }

                return ['report_id' => $id, 'code' => $code, 'revision' => $revision];
            }, 1); // File output must not be repeated by an implicit database deadlock retry.
        } catch (\Throwable $error) {
            if ($written) {
                Storage::disk('local')->delete($written);
            }
            throw $error;
        }
    }

    public function bytes(int $tenantId, User $actor, int $id): array
    {
        $this->access->assertContext($actor, $tenantId);
        abort_unless($this->access->manager($actor, $tenantId), 403);
        $report = DB::table('pre_distribution_reports')->where('tenant_id', $tenantId)->find($id);
        abort_unless($report, 404);
        $bytes = Storage::disk('local')->get($report->path);
        abort_unless(is_string($bytes) && hash_equals($report->sha256, hash('sha256', $bytes)), 409, 'O arquivo não está disponível ou sua integridade não confere. Emita uma nova revisão; o histórico anterior será preservado.');

        return ['bytes' => $bytes, 'filename' => 'relacao-preliminar-'.substr($report->series_id, 0, 8).'-v'.$report->revision.'.pdf'];
    }
}
