<?php

namespace App\Http\Controllers\Delivery;

use App\Enums\DeliveryConferenceStatus;
use App\Enums\DocumentCategory;
use App\Filament\Resources\CustomerBillingReceiptResource;
use App\Http\Controllers\Controller;
use App\Models\DeliveryConferenceSheet;
use App\Models\Document;
use App\Models\ProductionDelivery;
use App\Models\SalesProject;
use App\Services\DeliveryConferenceSheetService;
use App\Services\TemplatedPdfService;
use App\Services\TenantGoogleDriveService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class DeliveryConferenceSheetController extends Controller
{
    public function __construct(private readonly DeliveryConferenceSheetService $sheets)
    {
        $this->middleware('auth');
    }

    public function index(Request $request, $tenant)
    {
        $this->authorize('viewAny', DeliveryConferenceSheet::class);
        $tenantId = $this->tenantId();
        $query = DeliveryConferenceSheet::query()->where('tenant_id', $tenantId)
            ->with(['project:id,title', 'customer:id,name', 'organization:id,name'])
            ->withCount('distributions')
            ->when($request->integer('project'), fn ($q, int $id) => $q->where('sales_project_id', $id))
            ->when($request->string('status')->toString(), fn ($q, string $status) => $q->where('status', $status))
            ->latest('created_at');

        $projects = SalesProject::query()->where('tenant_id', $tenantId)
            ->with([
                'customer:id,tenant_id,name,trade_name,status',
                'customers' => fn ($q) => $q->where('customers.status', true)->select('customers.id', 'customers.tenant_id', 'customers.name', 'customers.trade_name', 'customers.status'),
                'organizations' => fn ($q) => $q->where('organizations.active', true)->select('organizations.id', 'organizations.tenant_id', 'organizations.name', 'organizations.active'),
            ])->orderByDesc('reference_year')->orderBy('title')->get(['id', 'tenant_id', 'customer_id', 'title', 'reference_year']);

        return response()->view('delivery.conference-sheets.index', [
            'sheets' => $query->paginate(20)->withQueryString(),
            'projects' => $projects,
            'statuses' => DeliveryConferenceStatus::cases(),
        ])->header('Cache-Control', 'no-store, private');
    }

    public function store(Request $request, $tenant)
    {
        $this->authorize('create', DeliveryConferenceSheet::class);
        $data = $request->validate([
            'sales_project_id' => ['required', 'integer'],
            'recipient_type' => ['required', Rule::in(['customer', 'organization'])],
            'recipient_id' => ['required', 'integer'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'grouping_mode' => ['required', Rule::in(['customer', 'organization_detailed', 'organization_consolidated'])],
        ]);
        $project = SalesProject::withoutGlobalScopes()->where('tenant_id', $this->tenantId())->findOrFail($data['sales_project_id']);
        $data[$data['recipient_type'].'_id'] = (int) $data['recipient_id'];
        $sheet = $this->sheets->createDraft($project, $data, $request->user());

        return redirect()->route('delivery.conference-sheets.show', [$tenant, $sheet])->with('success', 'Folha preparada. Confira e emita quando estiver correta.');
    }

    public function show(Request $request, $tenant, int $sheet)
    {
        $record = $this->sheet($sheet);
        $this->authorize('view', $record);
        $record->load(['project', 'customer', 'organization', 'documents.uploader', 'supersedes', 'revisions']);
        $valid = $record->snapshot_hash ? $this->sheets->isCurrentlyValid($record) : null;
        $distributionIds = collect(data_get($record->snapshot, 'distributions', []))->pluck('id');
        $billing = $distributionIds->isEmpty() ? collect() : ProductionDelivery::withoutGlobalScopes()
            ->where('tenant_id', $record->tenant_id)->whereIn('id', $distributionIds)->whereNotNull('billing_receipt_id')
            ->with('billingReceipt:id,receipt_year,receipt_number,receipt_label')->get()->pluck('billingReceipt')->filter()->unique('id')->values();
        $billedCount = $distributionIds->isEmpty() ? 0 : ProductionDelivery::withoutGlobalScopes()
            ->where('tenant_id', $record->tenant_id)->whereIn('id', $distributionIds)->whereNotNull('billing_receipt_id')->count();
        $coverage = $billedCount === 0 ? 'Não cobrada' : ($billedCount === $distributionIds->count() ? 'Totalmente cobrada' : 'Parcialmente cobrada');

        return response()->view('delivery.conference-sheets.show', compact('record', 'valid', 'billing', 'coverage'))
            ->header('Cache-Control', 'no-store, private');
    }

    public function preview(Request $request, $tenant, int $sheet)
    {
        $record = $this->sheet($sheet);
        $this->authorize('view', $record);

        return $this->pdf($record, $record->snapshot ?: $this->sheets->previewSnapshot($record), true);
    }

    public function download(Request $request, $tenant, int $sheet)
    {
        $record = $this->sheet($sheet);
        $this->authorize('view', $record);
        abort_unless($record->snapshot, 409, 'A folha ainda não foi emitida.');

        return $this->pdf($record, $record->snapshot, false);
    }

    public function issue(Request $request, $tenant, int $sheet)
    {
        $record = $this->sheet($sheet);
        $this->authorize('issue', $record);
        $issued = $this->sheets->issue($record, $request->user());

        return redirect()->route('delivery.conference-sheets.show', [$tenant, $issued])->with('success', 'Folha emitida e snapshot congelado com segurança.');
    }

    public function update(Request $request, $tenant, int $sheet)
    {
        $record = $this->sheet($sheet);
        $this->authorize('update', $record);
        $data = $request->validate([
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'grouping_mode' => ['required', Rule::in(['customer', 'organization_detailed', 'organization_consolidated'])],
        ]);
        $this->sheets->updateDraft($record, $data, $request->user());

        return back()->with('success', 'Período, modo e distribuições do rascunho foram atualizados.');
    }

    public function review(Request $request, $tenant, int $sheet)
    {
        $record = $this->sheet($sheet);
        $this->authorize('review', $record);
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approved', 'correction_requested', 'rejected'])],
            'review_note' => ['nullable', 'required_unless:decision,approved', 'string', 'min:5', 'max:2000'],
            'images' => ['nullable', 'array', 'max:12'],
            'images.*' => ['file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:10240'],
        ]);
        if ($request->hasFile('images')) {
            $this->storeDocuments($record, $request->file('images'), $request);
        }
        $this->sheets->review($record, $data['decision'], $data['review_note'] ?? null, $request->user());

        return back()->with('success', 'Resultado da conferência registrado.');
    }

    public function upload(Request $request, $tenant, int $sheet)
    {
        $record = $this->sheet($sheet);
        $this->authorize('upload', $record);
        $request->validate(['images' => ['required', 'array', 'max:12'], 'images.*' => ['file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:10240']]);
        $this->storeDocuments($record, $request->file('images'), $request);

        return back()->with('success', 'Imagem anexada como evidência documental.');
    }

    public function document(Request $request, $tenant, int $sheet, int $document)
    {
        $record = $this->sheet($sheet);
        $this->authorize('view', $record);
        $file = Document::withoutGlobalScopes()->where('tenant_id', $record->tenant_id)
            ->where('documentable_type', $record->getMorphClass())->where('documentable_id', $record->id)->findOrFail($document);
        abort_unless(Storage::disk($file->disk)->exists($file->path), 404);

        return Storage::disk($file->disk)->download($file->path, $file->original_name, ['Cache-Control' => 'no-store, private']);
    }

    public function revision(Request $request, $tenant, int $sheet)
    {
        $record = $this->sheet($sheet);
        $this->authorize('create', DeliveryConferenceSheet::class);
        abort_unless($record->status === DeliveryConferenceStatus::CORRECTION_REQUESTED, 409, 'Somente folhas com correção solicitada podem ser revisadas.');
        $draft = $this->sheets->createDraft($record->project, [
            'customer_id' => $record->customer_id, 'organization_id' => $record->organization_id,
            'period_start' => $record->period_start->format('Y-m-d'), 'period_end' => $record->period_end->format('Y-m-d'),
            'grouping_mode' => $record->grouping_mode->value,
        ], $request->user(), $record);

        return redirect()->route('delivery.conference-sheets.show', [$tenant, $draft])->with('success', 'Nova revisão criada sem alterar a folha anterior.');
    }

    public function cancel(Request $request, $tenant, int $sheet)
    {
        $record = $this->sheet($sheet);
        $this->authorize('cancel', $record);
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']]);
        $this->sheets->cancel($record, $data['reason'], $request->user());

        return back()->with('success', 'Folha cancelada; o histórico foi preservado.');
    }

    public function prepareBilling(Request $request, $tenant)
    {
        $ids = collect($request->validate(['sheet_ids' => ['required', 'array', 'min:1'], 'sheet_ids.*' => ['integer']])['sheet_ids'])->unique();
        $records = DeliveryConferenceSheet::withoutGlobalScopes()->where('tenant_id', $this->tenantId())->whereIn('id', $ids)->get();
        abort_unless($records->count() === $ids->count(), 404);
        foreach ($records as $record) {
            $this->authorize('prepareBilling', $record);
        }
        $receipt = $this->sheets->prepareBilling($records, $request->user());

        return redirect()->to(CustomerBillingReceiptResource::getUrl('edit', ['record' => $receipt]))
            ->with('success', 'Rascunho preparado. O serviço financeiro oficial revalidará preços, taxas e valores na emissão.');
    }

    private function sheet(int $id): DeliveryConferenceSheet
    {
        return DeliveryConferenceSheet::withoutGlobalScopes()->where('tenant_id', $this->tenantId())->findOrFail($id);
    }

    private function tenantId(): int
    {
        abort_unless((int) session('tenant_id') > 0, 403);

        return (int) session('tenant_id');
    }

    private function pdf(DeliveryConferenceSheet $sheet, array $snapshot, bool $inline)
    {
        $sheet->loadMissing('tenant');
        $options = app(TemplatedPdfService::class)->systemPdfOptions('pdf.delivery-conference-sheet', 'Folha de Conferência de Entregas', $sheet->project, $sheet->tenant_id);
        $pdf = app(TemplatedPdfService::class)->generateSystemPdf('pdf.delivery-conference-sheet', compact('sheet', 'snapshot'), $options)
            ->setOption('defaultFont', 'DejaVu Sans')->setOption('isHtml5ParserEnabled', true)->setOption('isRemoteEnabled', true);
        $filename = str_replace('/', '-', $sheet->formatted_number).'-r'.$sheet->revision.'.pdf';

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf', 'Cache-Control' => 'no-store, private',
            'Content-Disposition' => ($inline ? 'inline' : 'attachment').'; filename="'.$filename.'"',
        ]);
    }

    private function storeDocuments(DeliveryConferenceSheet $sheet, array $files, Request $request): void
    {
        foreach ($files as $position => $file) {
            $checksum = hash_file('sha256', $file->getRealPath());
            if ($sheet->documents()->where('notes', 'sha256:'.$checksum)->exists()) {
                continue;
            }
            $path = $file->store('delivery-conference/'.$sheet->tenant_id.'/'.$sheet->id, 'local');
            $document = $sheet->documents()->create([
                'tenant_id' => $sheet->tenant_id,
                'name' => 'Folha assinada - página '.($position + 1), 'original_name' => $file->getClientOriginalName(),
                'path' => $path, 'disk' => 'local', 'mime_type' => $file->getMimeType(), 'size' => $file->getSize(),
                'extension' => strtolower($file->getClientOriginalExtension()), 'category' => DocumentCategory::DELIVERY_CONFERENCE_SIGNED,
                'document_date' => now(), 'uploaded_by' => $request->user()->id, 'notes' => 'sha256:'.$checksum,
            ]);
            try {
                app(TenantGoogleDriveService::class)->putDocument(
                    $sheet->tenant, $document, 'delivery_conference_signed',
                    ['Projetos', $sheet->project->driveFolderName(), 'Folhas de Conferência', str_replace('/', '-', $sheet->formatted_number)],
                    $document->original_name, Storage::disk('local')->get($path), $document->mime_type
                );
            } catch (\Throwable) {
                // O arquivo local é a fonte disponível; a indisponibilidade do Drive não perde a evidência.
            }
            activity()->performedOn($sheet)->causedBy($request->user())->event('document_uploaded')
                ->withProperties(['tenant_id' => $sheet->tenant_id, 'document_id' => $document->id, 'checksum' => $checksum])
                ->log('Imagem da folha de conferência anexada');
        }
    }
}
