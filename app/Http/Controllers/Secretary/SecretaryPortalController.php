<?php

namespace App\Http\Controllers\Secretary;

use App\Http\Controllers\Controller;
use App\Models\DocumentTemplate;
use App\Models\GeneratedDocument;
use App\Models\PdfLayoutTemplate;
use App\Models\Tenant;
use App\Services\DocumentContentSanitizer;
use App\Services\TemplatedPdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SecretaryPortalController extends Controller
{
    public function __construct(private readonly DocumentContentSanitizer $sanitizer)
    {
        $this->middleware(['auth', 'any.role:secretario']);
    }

    public function index(Request $request): View
    {
        return view('secretary.index', ['tenant' => $this->tenant($request)]);
    }

    public function data(Request $request): JsonResponse
    {
        $tenant = $this->tenant($request);
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'kind' => ['nullable', 'in:all,documents,templates,system,layouts'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $search = trim((string) ($validated['search'] ?? ''));
        $kind = $validated['kind'] ?? 'all';

        $templates = collect();
        if (! in_array($kind, ['documents', 'layouts'], true)) {
            $templates = DocumentTemplate::query()
                ->where('tenant_id', $tenant->id)
                ->when($kind === 'templates', fn ($query) => $query->where('template_category', 'custom'))
                ->when($kind === 'system', fn ($query) => $query->where('template_category', 'system'))
                ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")->orWhere('description', 'like', "%{$search}%");
                }))
                ->latest('updated_at')
                ->limit(60)
                ->get(['id', 'name', 'type', 'template_category', 'system_template_key', 'description', 'is_active', 'updated_at'])
                ->map(fn (DocumentTemplate $template): array => $this->templatePayload($tenant, $template));
        }

        if ($kind === 'layouts') {
            $templates = PdfLayoutTemplate::query()->where('tenant_id', $tenant->id)
                ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
                ->latest('updated_at')->limit(60)->get(['id', 'name', 'layout_type', 'is_active', 'updated_at'])
                ->map(fn (PdfLayoutTemplate $layout): array => $this->layoutPayload($tenant, $layout));
        }

        $documents = null;
        if (! in_array($kind, ['templates', 'system', 'layouts'], true)) {
            $documents = GeneratedDocument::query()
                ->where('tenant_id', $tenant->id)
                ->with('template:id,tenant_id,name,type')
                ->when($search !== '', fn ($query) => $query->where('title', 'like', "%{$search}%"))
                ->latest('updated_at')
                ->paginate(12, ['id', 'tenant_id', 'template_id', 'title', 'status', 'signed_at', 'updated_at'])
                ->through(fn (GeneratedDocument $document): array => $this->documentPayload($tenant, $document));
        }

        return response()->json([
            'templates' => $templates,
            'documents' => $documents,
            'create_document_url' => route('secretary.documents.create', ['tenant' => $tenant->slug]),
            'create_template_url' => route('secretary.templates.create', ['tenant' => $tenant->slug]),
            'summary' => [
                'templates' => DocumentTemplate::query()->where('tenant_id', $tenant->id)->where('template_category', 'custom')->count(),
                'system' => DocumentTemplate::query()->where('tenant_id', $tenant->id)->where('template_category', 'system')->count(),
                'documents' => GeneratedDocument::query()->where('tenant_id', $tenant->id)->count(),
                'signed' => GeneratedDocument::query()->where('tenant_id', $tenant->id)->whereNotNull('signed_at')->count(),
                'layouts' => PdfLayoutTemplate::query()->where('tenant_id', $tenant->id)->count(),
            ],
        ])->header('Cache-Control', 'no-store, private');
    }

    public function createTemplate(Request $request): View
    {
        $tenant = $this->tenant($request);

        return $this->editorView($tenant, 'template', null, [
            'name' => '', 'description' => '', 'type' => 'minutes', 'template_category' => 'custom',
            'content' => '<h2>Novo documento</h2><p><br></p>', 'paper_size' => 'a4', 'paper_orientation' => 'portrait',
            'header_layout_id' => null, 'footer_layout_id' => null, 'is_active' => true,
        ]);
    }

    public function editTemplate(Request $request, Tenant $routeTenant, DocumentTemplate $template): View
    {
        $tenant = $this->tenant($request);
        $this->assertTenantRecord($template, $tenant);

        return $this->editorView($tenant, 'template', $template->id, [
            'name' => $template->name, 'description' => $template->description, 'type' => $template->type,
            'template_category' => $template->template_category, 'system_template_key' => $template->system_template_key,
            'project_type' => $template->project_type,
            'content' => $template->content, 'paper_size' => $template->paper_size ?: 'a4',
            'paper_orientation' => $template->paper_orientation ?: 'portrait', 'table_scale' => $template->table_scale ?: 100,
            'header_layout_id' => $template->header_layout_id, 'footer_layout_id' => $template->footer_layout_id,
            'visible_sections' => $template->visible_sections ?: [], 'visible_columns' => $template->visible_columns ?: [],
            'consent_enabled' => (bool) $template->consent_enabled, 'consent_position' => $template->consent_position ?: 'after',
            'consent_content_before' => $template->consent_content_before, 'consent_content' => $template->consent_content,
            'show_recipient_signature' => (bool) $template->show_recipient_signature,
            'show_representative_signature' => (bool) $template->show_representative_signature,
            'color_theme' => $template->color_theme ?: 'org',
            'is_active' => (bool) $template->is_active,
        ]);
    }

    public function storeTemplate(Request $request): JsonResponse
    {
        $tenant = $this->tenant($request);
        $validated = $this->validateTemplate($request, $tenant, false);
        $validated['content'] = $this->sanitizer->sanitize($validated['content']);
        $validated['tenant_id'] = $tenant->id;
        $validated['template_category'] = 'custom';
        $validated['created_by'] = $request->user()->id;
        $template = DocumentTemplate::query()->create($validated);

        $this->audit($request, $template, 'Modelo de documento criado');

        return response()->json(['message' => 'Modelo salvo.', 'id' => $template->id,
            'redirect_url' => route('secretary.templates.edit', ['tenant' => $tenant->slug, 'template' => $template])], 201);
    }

    public function updateTemplate(Request $request, Tenant $routeTenant, DocumentTemplate $template): JsonResponse
    {
        $tenant = $this->tenant($request);
        $this->assertTenantRecord($template, $tenant);
        $validated = $this->validateTemplate($request, $tenant, $template->template_category === 'system');
        if ($template->template_category === 'custom') {
            $validated['content'] = $this->sanitizer->sanitize($validated['content']);
        } else {
            unset($validated['content'], $validated['type']);
            foreach (['consent_content_before', 'consent_content'] as $field) {
                $value = trim((string) ($validated[$field] ?? ''));
                $validated[$field] = $value === '' ? null : $this->sanitizer->sanitize($value);
            }
            $definition = $template->getSystemDefinition() ?? [];
            $validated['visible_sections'] = array_values(array_intersect($validated['visible_sections'] ?? [], array_keys($definition['sections'] ?? [])));
            $validated['visible_columns'] = array_values(array_intersect($validated['visible_columns'] ?? [], array_keys($definition['columns'] ?? [])));
        }
        $template->update($validated);

        $this->audit($request, $template, 'Modelo de documento atualizado');

        return response()->json(['message' => 'Modelo atualizado.']);
    }

    public function createDocument(Request $request): View
    {
        $tenant = $this->tenant($request);
        $template = null;
        if ($request->integer('template')) {
            $template = DocumentTemplate::query()->where('tenant_id', $tenant->id)->where('template_category', 'custom')
                ->findOrFail($request->integer('template'));
        }
        $content = $template ? $this->resolveTemplateContent($template, $tenant) : '<h2>Novo documento</h2><p><br></p>';

        return $this->editorView($tenant, 'document', null, [
            'title' => $template?->name ?? 'Documento sem título', 'template_id' => $template?->id, 'content' => $content,
            'status' => 'draft', 'paper_size' => $template?->paper_size ?: 'a4',
            'paper_orientation' => $template?->paper_orientation ?: 'portrait',
            'header_layout_id' => $template?->header_layout_id, 'footer_layout_id' => $template?->footer_layout_id,
        ]);
    }

    public function editDocument(Request $request, Tenant $routeTenant, GeneratedDocument $document): View
    {
        $tenant = $this->tenant($request);
        $this->assertTenantRecord($document, $tenant);
        $document->load('template');
        $settings = $document->document_settings ?: [];

        return $this->editorView($tenant, 'document', $document->id, [
            'title' => $document->title, 'template_id' => $document->template_id, 'content' => $document->content,
            'status' => $document->status ?: 'draft', 'signed' => $document->signed_at !== null,
            'paper_size' => $settings['paper_size'] ?? $document->template?->paper_size ?? 'a4',
            'paper_orientation' => $settings['paper_orientation'] ?? $document->template?->paper_orientation ?? 'portrait',
            'header_layout_id' => $settings['header_layout_id'] ?? $document->template?->header_layout_id,
            'footer_layout_id' => $settings['footer_layout_id'] ?? $document->template?->footer_layout_id,
        ]);
    }

    public function storeDocument(Request $request): JsonResponse
    {
        $tenant = $this->tenant($request);
        $validated = $this->validateDocument($request, $tenant);
        $template = $this->documentTemplate($tenant, $validated['template_id'] ?? null, $request->user()->id);
        $document = DB::transaction(function () use ($validated, $template, $tenant, $request): GeneratedDocument {
            return GeneratedDocument::query()->create([
                'tenant_id' => $tenant->id, 'template_id' => $template->id, 'title' => $validated['title'],
                'content' => $this->sanitizer->sanitize($validated['content']), 'status' => 'draft',
                'document_settings' => $this->documentSettings($validated), 'generated_by' => $request->user()->id,
                'last_edited_by' => $request->user()->id,
            ]);
        });
        $this->audit($request, $document, 'Documento criado');

        return response()->json(['message' => 'Rascunho salvo.', 'id' => $document->id,
            'redirect_url' => route('secretary.documents.edit', ['tenant' => $tenant->slug, 'document' => $document])], 201);
    }

    public function updateDocument(Request $request, Tenant $routeTenant, GeneratedDocument $document): JsonResponse
    {
        $tenant = $this->tenant($request);
        $this->assertTenantRecord($document, $tenant);
        abort_if($document->signed_at !== null, 409, 'Documento assinado não pode ser alterado.');
        $validated = $this->validateDocument($request, $tenant);
        if (! empty($validated['template_id'])) {
            DocumentTemplate::query()->where('tenant_id', $tenant->id)->where('template_category', 'custom')->findOrFail($validated['template_id']);
        }
        $document->update([
            'title' => $validated['title'], 'content' => $this->sanitizer->sanitize($validated['content']),
            'template_id' => ($validated['template_id'] ?? null) ?: $document->template_id,
            'document_settings' => $this->documentSettings($validated), 'last_edited_by' => $request->user()->id,
        ]);
        $this->audit($request, $document, 'Documento atualizado');

        return response()->json(['message' => 'Alterações salvas.', 'updated_at' => $document->updated_at?->format('H:i')]);
    }

    public function destroyDocument(Request $request, Tenant $routeTenant, GeneratedDocument $document): JsonResponse
    {
        $tenant = $this->tenant($request);
        $this->assertTenantRecord($document, $tenant);
        abort_if($document->signed_at !== null || ($document->status && $document->status !== 'draft'), 409, 'Somente rascunhos podem ser excluídos.');
        $this->audit($request, $document, 'Rascunho de documento excluído');
        $document->delete();

        return response()->json(['message' => 'Rascunho excluído.']);
    }

    public function destroyTemplate(Request $request, Tenant $routeTenant, DocumentTemplate $template): JsonResponse
    {
        $tenant = $this->tenant($request);
        $this->assertTenantRecord($template, $tenant);
        abort_if($template->template_category === 'system', 409, 'Modelos de PDF do sistema devem ser desativados, não excluídos.');
        abort_if($template->generatedDocuments()->exists(), 409, 'Este modelo já possui documentos. Desative-o para preservar o histórico.');
        $this->audit($request, $template, 'Modelo de documento excluído');
        $template->delete();

        return response()->json(['message' => 'Modelo excluído.']);
    }

    public function createLayout(Request $request): View
    {
        $tenant = $this->tenant($request);

        return $this->editorView($tenant, 'layout', null, [
            'name' => '', 'layout_type' => 'header', 'content' => '<p><strong>{{cooperativa.nome}}</strong></p>',
            'estimated_height_mm' => 22, 'is_active' => true,
        ]);
    }

    public function editLayout(Request $request, Tenant $routeTenant, PdfLayoutTemplate $layout): View
    {
        $tenant = $this->tenant($request);
        $this->assertTenantRecord($layout, $tenant);

        return $this->editorView($tenant, 'layout', $layout->id, [
            'name' => $layout->name, 'layout_type' => $layout->layout_type, 'content' => $layout->content,
            'estimated_height_mm' => $layout->estimated_height_mm ?: 22, 'is_active' => (bool) $layout->is_active,
        ]);
    }

    public function storeLayout(Request $request): JsonResponse
    {
        $tenant = $this->tenant($request);
        $validated = $this->validateLayout($request);
        $validated['content'] = $this->sanitizer->sanitize($validated['content']);
        $validated['tenant_id'] = $tenant->id;
        $validated['created_by'] = $request->user()->id;
        $layout = PdfLayoutTemplate::query()->create($validated);
        $this->audit($request, $layout, 'Layout de PDF criado');

        return response()->json(['message' => 'Layout salvo.', 'id' => $layout->id,
            'redirect_url' => route('secretary.layouts.edit', ['tenant' => $tenant->slug, 'layout' => $layout])], 201);
    }

    public function updateLayout(Request $request, Tenant $routeTenant, PdfLayoutTemplate $layout): JsonResponse
    {
        $tenant = $this->tenant($request);
        $this->assertTenantRecord($layout, $tenant);
        $validated = $this->validateLayout($request);
        $validated['content'] = $this->sanitizer->sanitize($validated['content']);
        $layout->update($validated);
        $this->audit($request, $layout, 'Layout de PDF atualizado');

        return response()->json(['message' => 'Layout atualizado.']);
    }

    public function destroyLayout(Request $request, Tenant $routeTenant, PdfLayoutTemplate $layout): JsonResponse
    {
        $tenant = $this->tenant($request);
        $this->assertTenantRecord($layout, $tenant);
        $used = DocumentTemplate::query()->where('tenant_id', $tenant->id)->where(function ($query) use ($layout): void {
            $query->where('header_layout_id', $layout->id)->orWhere('footer_layout_id', $layout->id)
                ->orWhere('cover_layout_id', $layout->id)->orWhere('back_cover_layout_id', $layout->id);
        })->exists();
        abort_if($used, 409, 'Este layout está em uso. Substitua-o nos modelos antes de excluir.');
        $this->audit($request, $layout, 'Layout de PDF excluído');
        $layout->delete();

        return response()->json(['message' => 'Layout excluído.']);
    }

    public function previewTemplate(Request $request, Tenant $routeTenant, DocumentTemplate $template, TemplatedPdfService $pdfService)
    {
        $tenant = $this->tenant($request);
        $this->assertTenantRecord($template, $tenant);
        abort_unless($template->template_category === 'custom', 422, 'Use os fluxos do sistema para pré-visualizar este PDF.');

        return $this->inlinePdf($pdfService->generateCustomTemplate($template), Str::slug($template->name).'.pdf');
    }

    public function previewDocument(Request $request, Tenant $routeTenant, GeneratedDocument $document, TemplatedPdfService $pdfService)
    {
        $tenant = $this->tenant($request);
        $this->assertTenantRecord($document, $tenant);
        $document->load('template');
        $settings = $document->document_settings ?: [];
        $template = $document->template->replicate();
        $template->content = $this->sanitizer->sanitize($document->content);
        foreach (['paper_size', 'paper_orientation', 'header_layout_id', 'footer_layout_id'] as $key) {
            if (array_key_exists($key, $settings)) {
                $template->{$key} = $settings[$key];
            }
        }
        $template->name = $document->title;

        return $this->inlinePdf($pdfService->generateCustomTemplate($template), Str::slug($document->title).'.pdf');
    }

    public function uploadImage(Request $request): JsonResponse
    {
        $tenant = $this->tenant($request);
        $validated = $request->validate(['image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120']]);
        $file = $validated['image'];
        $name = (string) Str::ulid().'.'.strtolower($file->getClientOriginalExtension());
        $path = $file->storeAs('tenants/'.$tenant->id.'/secretary/images', $name, 'public');

        return response()->json(['url' => Storage::disk('public')->url($path)], 201);
    }

    private function validateTemplate(Request $request, Tenant $tenant, bool $system): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:180'], 'description' => ['nullable', 'string', 'max:500'],
            'paper_size' => ['required', Rule::in(array_keys(DocumentTemplate::PAPER_SIZES))],
            'paper_orientation' => ['required', Rule::in(array_keys(DocumentTemplate::PAPER_ORIENTATIONS))],
            'header_layout_id' => ['nullable', 'integer'], 'footer_layout_id' => ['nullable', 'integer'],
            'is_active' => ['required', 'boolean'],
        ];
        if ($system) {
            $rules += ['visible_sections' => ['array'], 'visible_sections.*' => ['string'], 'visible_columns' => ['array'],
                'visible_columns.*' => ['string'], 'table_scale' => ['required', Rule::in([70, 80, 90, 100])],
                'consent_enabled' => ['required', 'boolean'], 'consent_position' => ['required', Rule::in(['before', 'after', 'both'])],
                'consent_content_before' => ['nullable', 'string', 'max:100000'], 'consent_content' => ['nullable', 'string', 'max:100000'],
                'show_recipient_signature' => ['required', 'boolean'], 'show_representative_signature' => ['required', 'boolean'],
                'color_theme' => ['required', Rule::in(array_keys(DocumentTemplate::COLOR_THEMES))]];
            $rules['project_type'] = ['nullable', 'string', 'max:80'];
        } else {
            $rules += ['type' => ['required', Rule::in(array_keys(DocumentTemplate::TYPES))], 'content' => ['required', 'string', 'max:1500000']];
        }
        $validated = $request->validate($rules);
        $this->validateLayouts($tenant, $validated);

        return $validated;
    }

    private function validateDocument(Request $request, Tenant $tenant): array
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:220'], 'content' => ['required', 'string', 'max:1500000'],
            'template_id' => ['nullable', 'integer'], 'paper_size' => ['required', Rule::in(array_keys(DocumentTemplate::PAPER_SIZES))],
            'paper_orientation' => ['required', Rule::in(array_keys(DocumentTemplate::PAPER_ORIENTATIONS))],
            'header_layout_id' => ['nullable', 'integer'], 'footer_layout_id' => ['nullable', 'integer'],
        ]);
        $this->validateLayouts($tenant, $validated);

        return $validated;
    }

    private function validateLayout(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'layout_type' => ['required', Rule::in(array_keys(PdfLayoutTemplate::LAYOUT_TYPES))],
            'content' => ['required', 'string', 'max:500000'],
            'estimated_height_mm' => ['required', 'integer', 'min:8', 'max:100'],
            'is_active' => ['required', 'boolean'],
        ]);
    }

    private function validateLayouts(Tenant $tenant, array $data): void
    {
        foreach (['header_layout_id', 'footer_layout_id'] as $key) {
            if (! empty($data[$key])) {
                abort_unless(PdfLayoutTemplate::query()->where('tenant_id', $tenant->id)->whereKey($data[$key])->exists(), 422, 'Layout inválido para esta organização.');
            }
        }
    }

    private function documentTemplate(Tenant $tenant, ?int $templateId, int $userId): DocumentTemplate
    {
        if ($templateId) {
            return DocumentTemplate::query()->where('tenant_id', $tenant->id)->where('template_category', 'custom')->findOrFail($templateId);
        }

        return DocumentTemplate::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Documento avulso', 'template_category' => 'custom', 'is_active' => false],
            ['type' => 'other', 'description' => 'Modelo interno para documentos avulsos', 'content' => '<p><br></p>',
                'paper_size' => 'a4', 'paper_orientation' => 'portrait', 'created_by' => $userId],
        );
    }

    private function documentSettings(array $data): array
    {
        return collect($data)->only(['paper_size', 'paper_orientation', 'header_layout_id', 'footer_layout_id'])->all();
    }

    private function resolveTemplateContent(DocumentTemplate $template, Tenant $tenant): string
    {
        $variables = app(TemplatedPdfService::class)->resolveSystemVariables($tenant);
        $content = (string) $template->content;
        foreach ($variables as $key => $value) {
            $content = str_replace($key, (string) $value, $content);
        }

        return $this->sanitizer->sanitize($content);
    }

    private function editorView(Tenant $tenant, string $mode, ?int $recordId, array $record): View
    {
        foreach (['content', 'consent_content_before', 'consent_content'] as $htmlField) {
            if (! empty($record[$htmlField])) {
                $record[$htmlField] = $this->sanitizer->sanitize((string) $record[$htmlField]);
            }
        }
        $layouts = PdfLayoutTemplate::query()->where('tenant_id', $tenant->id)->where('is_active', true)
            ->orderBy('name')->get(['id', 'name', 'layout_type'])->groupBy('layout_type');
        $templates = DocumentTemplate::query()->where('tenant_id', $tenant->id)->where('template_category', 'custom')
            ->where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $definition = ! empty($record['system_template_key'])
            ? (DocumentTemplate::getSystemTemplateDefinitions()[$record['system_template_key']] ?? []) : [];

        $variables = DocumentTemplate::getAvailableVariables();
        $variables['Layout e página'] = PdfLayoutTemplate::getAvailableVariables();

        return view('secretary.editor', compact('tenant', 'mode', 'recordId', 'record', 'layouts', 'templates', 'definition') + [
            'variables' => $variables,
        ]);
    }

    private function templatePayload(Tenant $tenant, DocumentTemplate $template): array
    {
        return ['id' => (int) $template->id, 'name' => $template->name,
            'type' => DocumentTemplate::TYPES[$template->type] ?? 'Documento', 'category' => $template->template_category,
            'description' => $template->description, 'active' => (bool) $template->is_active,
            'updated_at' => $template->updated_at?->format('d/m/Y'),
            'edit_url' => route('secretary.templates.edit', ['tenant' => $tenant->slug, 'template' => $template]),
            'use_url' => $template->template_category === 'custom'
                ? route('secretary.documents.create', ['tenant' => $tenant->slug, 'template' => $template]) : null];
    }

    private function documentPayload(Tenant $tenant, GeneratedDocument $document): array
    {
        return ['id' => (int) $document->id, 'title' => $document->title, 'template' => $document->template?->name,
            'type' => DocumentTemplate::TYPES[$document->template?->type] ?? 'Documento',
            'signed' => $document->signed_at !== null, 'status' => $document->status ?: 'draft',
            'updated_at' => $document->updated_at?->format('d/m/Y H:i'),
            'edit_url' => route('secretary.documents.edit', ['tenant' => $tenant->slug, 'document' => $document]),
            'preview_url' => route('secretary.documents.preview', ['tenant' => $tenant->slug, 'document' => $document])];
    }

    private function layoutPayload(Tenant $tenant, PdfLayoutTemplate $layout): array
    {
        return ['id' => (int) $layout->id, 'name' => $layout->name,
            'type' => PdfLayoutTemplate::LAYOUT_TYPES[$layout->layout_type] ?? 'Layout', 'category' => 'layout',
            'description' => $layout->is_active ? 'Ativo' : 'Inativo', 'active' => (bool) $layout->is_active,
            'updated_at' => $layout->updated_at?->format('d/m/Y'), 'use_url' => null,
            'edit_url' => route('secretary.layouts.edit', ['tenant' => $tenant->slug, 'layout' => $layout])];
    }

    private function inlinePdf($pdf, string $filename)
    {
        return response($pdf->output(), 200, ['Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"', 'Cache-Control' => 'no-store, private']);
    }

    private function assertTenantRecord($record, Tenant $tenant): void
    {
        abort_unless((int) $record->tenant_id === (int) $tenant->id, 404);
    }

    private function audit(Request $request, $record, string $message): void
    {
        if (! Schema::hasTable('activity_log')) {
            return;
        }

        activity()
            ->performedOn($record)
            ->causedBy($request->user())
            ->withProperties(['tenant_id' => $record->tenant_id])
            ->log($message);
    }

    private function tenant(Request $request): Tenant
    {
        $tenant = $request->route('tenant');
        abort_unless($tenant instanceof Tenant, 404);
        abort_unless((int) session('tenant_id') === (int) $tenant->id, 403);
        $user = $request->user();
        abort_unless($user?->tenants()->where('tenants.id', $tenant->id)->wherePivot('status', true)->exists(), 403);
        abort_unless(
            $user->hasAnyRole(['super_admin', 'admin']) || $user->hasRoleInTenant(['secretario'], $tenant->id),
            403,
        );

        return $tenant;
    }
}
