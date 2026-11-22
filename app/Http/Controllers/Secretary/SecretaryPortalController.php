<?php

namespace App\Http\Controllers\Secretary;

use App\Http\Controllers\Controller;
use App\Jobs\SyncOfficialLetterToDrive;
use App\Models\DocumentTemplate;
use App\Models\GeneratedDocument;
use App\Models\PdfLayoutTemplate;
use App\Models\Tenant;
use App\Models\TenantCloudStorageConnection;
use App\Services\DocumentContentSanitizer;
use App\Services\OfficialLetterService;
use App\Services\SecretaryAccess;
use App\Services\SecretaryDocumentAssets;
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
    public function __construct(
        private readonly DocumentContentSanitizer $sanitizer,
        private readonly SecretaryDocumentAssets $assets,
        private readonly OfficialLetterService $letters,
        private readonly SecretaryAccess $access,
    ) {}

    public function index(Request $request): View
    {
        $tenant = $this->tenant($request);

        return view('secretary.index', ['tenant' => $tenant,
            'canCreate' => $this->access->allows($request->user(), $tenant, 'create'),
            'canManageTemplates' => $this->access->allows($request->user(), $tenant, 'templates'),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $tenant = $this->tenant($request);
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'kind' => ['nullable', 'in:all,documents,templates,system,layouts'],
            'page' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'in:draft,issued,cancelled'],
            'type' => ['nullable', Rule::in(array_keys(DocumentTemplate::TYPES))],
        ]);
        $search = trim((string) ($validated['search'] ?? ''));
        $kind = $validated['kind'] ?? 'all';
        $templates = collect();
        if (! in_array($kind, ['documents', 'layouts'], true)) {
            $templates = DocumentTemplate::query()
                ->where('tenant_id', $tenant->id)
                ->whereNot(fn ($query) => $query->where('type', OfficialLetterService::TYPE)
                    ->where('name', OfficialLetterService::STANDARD_TEMPLATE)->where('is_active', false))
                ->when($kind === 'templates', fn ($query) => $query->where('template_category', 'custom'))
                ->when($kind === 'system', fn ($query) => $query->where('template_category', 'system'))
                ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")->orWhere('description', 'like', "%{$search}%");
                }))
                ->latest('updated_at')
                ->limit(60)
                ->get(['id', 'name', 'type', 'template_category', 'system_template_key', 'description', 'is_active', 'updated_at'])
                ->map(fn (DocumentTemplate $template): array => $this->templatePayload($tenant, $template));
            if (in_array($kind, ['all', 'templates'], true) && ($search === '' || Str::contains(Str::lower('Ofício institucional'), Str::lower($search)))) {
                $templates->prepend([
                    'id' => 0, 'name' => 'Ofício institucional', 'type' => 'Ofício', 'category' => 'institutional',
                    'description' => 'Modelo institucional do SGC', 'active' => true, 'updated_at' => null,
                    'use_url' => route('secretary.documents.create', ['tenant' => $tenant->slug, 'standard' => 1]),
                    'edit_url' => route('secretary.templates.create', ['tenant' => $tenant->slug, 'standard' => 1]),
                ]);
            }
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
                ->whereNull('documentable_type')
                ->with(['template:id,tenant_id,name,type', 'generator:id,name'])
                ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
                ->when($validated['type'] ?? null, fn ($query, $type) => $query->whereHas('template', fn ($template) => $template->where('type', $type)))
                ->when($search !== '', fn ($query) => $query->where(fn ($searchQuery) => $searchQuery
                    ->where('title', 'like', "%{$search}%")
                    ->orWhere('document_settings->letter->subject', 'like', "%{$search}%")
                    ->orWhere('document_settings->letter->recipient', 'like', "%{$search}%")
                    ->orWhere('document_settings->letter->institution', 'like', "%{$search}%")
                    ->orWhere('document_settings->number', 'like', "%{$search}%")))
                ->latest('updated_at')
                ->paginate(12, ['id', 'tenant_id', 'template_id', 'title', 'status', 'signed_at', 'updated_at', 'document_settings', 'generated_by'])
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
                'documents' => GeneratedDocument::query()->where('tenant_id', $tenant->id)->whereNull('documentable_type')->count(),
                'signed' => GeneratedDocument::query()->where('tenant_id', $tenant->id)->whereNull('documentable_type')
                    ->where(fn ($query) => $query->whereNotNull('signed_at')->orWhere('status', 'issued'))->count(),
                'layouts' => PdfLayoutTemplate::query()->where('tenant_id', $tenant->id)->count(),
            ],
        ])->header('Cache-Control', 'no-store, private');
    }

    public function createTemplate(Request $request): View
    {
        $tenant = $this->tenant($request);

        $source = $request->integer('from') ? DocumentTemplate::query()->where('tenant_id', $tenant->id)
            ->where('template_category', 'custom')->findOrFail($request->integer('from')) : null;

        return $this->editorView($tenant, 'template', null, [
            'name' => $source ? Str::limit($source->name, 160, '').' (cópia)' : ($request->boolean('standard') ? 'Ofício institucional personalizado' : ''),
            'description' => $source?->description ?? '', 'type' => $source?->type ?? OfficialLetterService::TYPE, 'template_category' => 'custom',
            'content' => $source?->content ?? '<p><br></p>', 'paper_size' => $source?->paper_size ?? 'a4',
            'paper_orientation' => $source?->paper_orientation ?? 'portrait',
            'header_layout_id' => $source?->header_layout_id, 'footer_layout_id' => $source?->footer_layout_id, 'is_active' => true,
            'layout' => array_replace($source?->institutional_settings ?? [], ['default' => false]),
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
            'sales_project_id' => $template->sales_project_id, 'customer_id' => $template->customer_id,
            'organization_id' => $template->organization_id, 'report_contents' => $template->report_contents ?? [],
            'section_order' => $template->section_order ?? [],
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
            'layout' => $template->institutional_settings ?? [],
        ]);
    }

    public function storeTemplate(Request $request): JsonResponse
    {
        $tenant = $this->tenant($request);
        $validated = $this->validateTemplate($request, $tenant, false);
        $validated['content'] = $this->assets->clean($tenant, $validated['content']);
        $validated['tenant_id'] = $tenant->id;
        $validated['template_category'] = 'custom';
        $validated['created_by'] = $request->user()->id;
        $template = new DocumentTemplate();
        if ($validated['type'] === OfficialLetterService::TYPE) {
            $this->letters->setDefault($template, $validated, $tenant);
        } else {
            $template->fill($validated)->save();
        }

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
            $validated['content'] = $this->assets->clean($tenant, $validated['content'], previousHtml: $template->content);
            abort_if($template->type !== $validated['type'] && $template->generatedDocuments()->withTrashed()->exists(), 409, 'Duplique o modelo para alterar o tipo.');
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
        if (($validated['type'] ?? null) === OfficialLetterService::TYPE) {
            $this->letters->setDefault($template, $validated, $tenant);
        } else {
            $template->update($validated);
        }

        $this->audit($request, $template, 'Modelo de documento atualizado');

        return response()->json(['message' => 'Modelo atualizado.']);
    }

    public function createDocument(Request $request): View
    {
        $tenant = $this->tenant($request);
        if ($sourceId = $request->integer('from_document')) {
            $source = GeneratedDocument::query()->where('tenant_id', $tenant->id)->findOrFail($sourceId);
            $this->assertSecretaryDocument($source, $tenant);
            $settings = $source->document_settings ?? [];

            return $this->editorView($tenant, 'document', null, [
                'title' => Str::limit($source->title, 200, '').' (cópia)', 'template_id' => $source->template_id,
                'content' => $source->content, 'status' => 'draft', 'official' => $this->isLetter($source),
                'letter' => array_replace($settings['letter'] ?? [], ['date' => now('America/Sao_Paulo')->toDateString()]),
            ] + collect($settings)->only(['layout', 'paper_size', 'paper_orientation', 'header_layout_id', 'footer_layout_id'])->all());
        }
        $template = null;
        if ($request->integer('template')) {
            $template = DocumentTemplate::query()->where('tenant_id', $tenant->id)->where('template_category', 'custom')
                ->where('is_active', true)->findOrFail($request->integer('template'));
        } elseif (! $request->boolean('standard') && ! $request->boolean('example')) {
            $template = DocumentTemplate::query()->where('tenant_id', $tenant->id)->where('type', OfficialLetterService::TYPE)
                ->where('is_active', true)->where('institutional_settings->default', true)->first();
        }
        $official = ! $template || $template->type === OfficialLetterService::TYPE;
        $content = $official ? ($template?->content ?? '<p><br></p>') : $this->resolveTemplateContent($template, $tenant);
        $letter = $this->letters->defaults($tenant);
        if ($request->boolean('example') && $tenant->slug === 'ascoppa') {
            $letter = array_replace($letter, [
                'institution' => 'CODEVASF', 'subject' => 'Avaliação e manutenção de máquinas e equipamentos',
                'salutation' => 'Prezados Senhores,', 'date' => null,
            ]);
            $content = '<p>Solicitamos apoio técnico para a avaliação e a manutenção das máquinas de costura utilizadas pela associação, incluindo o reparo de uma máquina que deixou de funcionar.</p>'
                .'<p>Solicitamos também a avaliação da folga mecânica no conjunto utilizado no gradeamento e a verificação da necessidade de troca de óleo do trator.</p>';
        }

        return $this->editorView($tenant, 'document', null, [
            'title' => $template?->name ?? 'Ofício institucional', 'template_id' => $template?->id, 'content' => $content,
            'official' => $official, 'letter' => $letter, 'layout' => $template?->institutional_settings ?? [],
            'status' => 'draft', 'paper_size' => $template?->paper_size ?: 'a4',
            'paper_orientation' => $template?->paper_orientation ?: 'portrait',
            'header_layout_id' => $template?->header_layout_id, 'footer_layout_id' => $template?->footer_layout_id,
        ]);
    }

    public function editDocument(Request $request, Tenant $routeTenant, GeneratedDocument $document): View
    {
        $tenant = $this->tenant($request);
        $this->assertSecretaryDocument($document, $tenant);
        $document->load('template');
        $settings = $document->document_settings ?: [];

        return $this->editorView($tenant, 'document', $document->id, [
            'title' => $document->title, 'template_id' => $document->template_id, 'content' => $document->content,
            'status' => $document->status ?: 'draft',
            'signed' => $document->signed_at !== null || $document->status !== 'draft' || ! $this->access->allows($request->user(), $tenant, 'edit'),
            'official' => $this->isLetter($document), 'letter' => $settings['letter'] ?? [],
            'layout' => $settings['layout'] ?? [], 'revision' => $settings['revision'] ?? 0,
            'number' => $settings['number'] ?? null, 'cancellation' => $settings['cancellation_reason'] ?? null,
            'history' => $this->isLetter($document) ? DB::table('generated_document_revisions as revisions')->leftJoin('users', 'users.id', 'revisions.user_id')
                ->where('generated_document_id', $document->id)->latest('revision')->limit(50)
                ->get(['revision', 'event', 'name', 'revisions.created_at']) : [],
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
        $template = ! empty($validated['letter']) && empty($validated['template_id'])
            ? $this->letters->template($tenant, $request->user()->id)
            : $this->documentTemplate($tenant, $validated['template_id'] ?? null, $request->user()->id);
        $document = DB::transaction(function () use ($validated, $template, $tenant, $request): GeneratedDocument {
            $official = $template->type === OfficialLetterService::TYPE;
            $document = GeneratedDocument::query()->forceCreate([
                'tenant_id' => $tenant->id, 'template_id' => $template->id, 'title' => $validated['title'],
                'content' => $this->assets->clean($tenant, $validated['content']), 'status' => 'draft',
                'document_settings' => $this->documentSettings($validated, $official, $template) + ($official ? ['revision' => 1] : []),
                'generated_by' => $request->user()->id,
                'last_edited_by' => $request->user()->id,
            ]);
            if ($official) {
                $this->letters->snapshot($document, 'created', $request->user()->id);
            }
            return $document;
        });
        $this->audit($request, $document, 'Documento criado');

        return response()->json(['message' => 'Rascunho salvo.', 'id' => $document->id,
            'revision' => $document->document_settings['revision'] ?? null,
            'recovery_key' => 'sgc-secretary:'.$request->user()->id.':'.$tenant->id.':'.$document->id,
            'preview_url' => route('secretary.documents.preview', ['tenant' => $tenant->slug, 'document' => $document]),
            'redirect_url' => route('secretary.documents.edit', ['tenant' => $tenant->slug, 'document' => $document])], 201);
    }

    public function updateDocument(Request $request, Tenant $routeTenant, GeneratedDocument $document): JsonResponse
    {
        $tenant = $this->tenant($request);
        $this->assertSecretaryDocument($document, $tenant);
        $validated = $this->validateDocument($request, $tenant);
        $document = DB::transaction(function () use ($request, $document, $tenant, $validated) {
            $locked = GeneratedDocument::query()->where('tenant_id', $tenant->id)->lockForUpdate()->findOrFail($document->id);
            $official = $this->isLetter($locked);
            abort_unless($locked->status === 'draft' && $locked->signed_at === null, 409, 'Este documento está fechado para edição.');
            $revision = (int) ($locked->document_settings['revision'] ?? 0);
            if ($official) {
                $this->letters->assertRevision($locked, isset($validated['revision']) ? (int) $validated['revision'] : null);
            }
            $template = empty($validated['template_id']) ? $locked->template : DocumentTemplate::query()
                ->where('tenant_id', $tenant->id)->where('template_category', 'custom')->findOrFail($validated['template_id']);
            abort_if($official !== ($template?->type === OfficialLetterService::TYPE), 422, 'Não é possível alterar o tipo de um documento existente.');
            $locked->update([
                'title' => $validated['title'], 'content' => $this->assets->clean($tenant, $validated['content'], previousHtml: $locked->content),
                'template_id' => $template?->id ?? $locked->template_id,
                'document_settings' => $this->documentSettings($validated, $official, $template) + ($official ? ['revision' => $revision + 1] : []),
                'last_edited_by' => $request->user()->id,
            ]);
            if ($official && empty($validated['autosave'])) {
                $this->letters->snapshot($locked, 'saved', $request->user()->id);
            }
            return $locked;
        });
        if (empty($validated['autosave'])) {
            $this->audit($request, $document, 'Documento atualizado');
        }

        return response()->json(['message' => 'Alterações salvas.', 'updated_at' => $document->updated_at?->format('H:i'), 'revision' => $document->document_settings['revision'] ?? null]);
    }

    public function destroyDocument(Request $request, Tenant $routeTenant, GeneratedDocument $document): JsonResponse
    {
        $tenant = $this->tenant($request);
        $this->assertSecretaryDocument($document, $tenant);
        DB::transaction(function () use ($request, $document, $tenant): void {
            $locked = GeneratedDocument::query()->where('tenant_id', $tenant->id)->lockForUpdate()->findOrFail($document->id);
            abort_if($locked->signed_at !== null || ($locked->status && $locked->status !== 'draft'), 409, 'Somente rascunhos podem ser excluídos.');
            $this->audit($request, $locked, 'Rascunho de documento excluído');
            $locked->delete();
        });

        return response()->json(['message' => 'Rascunho excluído.']);
    }

    public function destroyTemplate(Request $request, Tenant $routeTenant, DocumentTemplate $template): JsonResponse
    {
        $tenant = $this->tenant($request);
        $this->assertTenantRecord($template, $tenant);
        abort_if($template->template_category === 'system', 409, 'Modelos de PDF do sistema devem ser desativados, não excluídos.');
        abort_if($template->generatedDocuments()->withTrashed()->exists(), 409, 'Este modelo já possui documentos. Desative-o para preservar o histórico.');
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
        $validated['content'] = $this->assets->clean($tenant, $validated['content']);
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
        $validated['content'] = $this->assets->clean($tenant, $validated['content'], previousHtml: $layout->content);
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

        if ($template->type === OfficialLetterService::TYPE) {
            $document = new GeneratedDocument(['title' => $template->name, 'content' => $template->content,
                'document_settings' => $this->documentSettings(['letter' => $this->letters->defaults($tenant)], true, $template)]);

            return $this->pdfResponse($this->letters->render($tenant, $document)['bytes'], Str::slug($template->name).'.pdf');
        }
        $template->content = $this->assets->clean($tenant, $template->content, true, $template->content);

        return $this->inlinePdf($pdfService->generateCustomTemplate($template), Str::slug($template->name).'.pdf');
    }

    public function previewDocument(Request $request, Tenant $routeTenant, GeneratedDocument $document, TemplatedPdfService $pdfService)
    {
        $tenant = $this->tenant($request);
        $this->assertSecretaryDocument($document, $tenant);
        $document->load('template');
        $settings = $document->document_settings ?: [];
        if ($this->isLetter($document)) {
            $filename = Str::slug(($settings['number'] ?? 'rascunho').' '.$document->title).'.pdf';
            $disposition = $request->boolean('download') ? 'attachment' : 'inline';
            if (in_array($document->status, ['issued', 'cancelled'], true)) {
                $path = $settings['pdf'] ?? '';
                abort_unless(str_starts_with($path, 'tenants/'.$tenant->id.'/secretary/issued/') && Storage::disk('local')->exists($path), 404, 'PDF arquivado não encontrado.');

                return Storage::disk('local')->response($path, $filename, [
                    'Content-Type' => 'application/pdf', 'Cache-Control' => 'private, no-store',
                ], $disposition);
            }

            return $this->pdfResponse($this->letters->render($tenant, $document)['bytes'], $filename, $disposition);
        }
        abort_unless($document->template, 404);
        $template = $document->template->replicate();
        $template->content = $this->assets->clean($tenant, $document->content, true, $document->content);
        foreach (['paper_size', 'paper_orientation', 'header_layout_id', 'footer_layout_id'] as $key) {
            if (array_key_exists($key, $settings)) {
                $template->{$key} = $settings[$key];
            }
        }
        $template->name = $document->title;

        return $this->inlinePdf($pdfService->generateCustomTemplate($template), Str::slug($document->title).'.pdf');
    }

    public function issueDocument(Request $request, Tenant $routeTenant, GeneratedDocument $document): JsonResponse
    {
        $tenant = $this->tenant($request);
        $this->assertSecretaryDocument($document, $tenant);
        abort_unless($this->isLetter($document), 422, 'A emissão com numeração está disponível para ofícios.');
        $data = $request->validate(['revision' => ['required', 'integer', 'min:1']]);
        $document = $this->letters->issue($tenant, $document, $request->user()->id, (int) $data['revision']);
        $this->queueDriveCopy($document);

        return response()->json(['message' => 'Ofício emitido.', 'number' => $document->document_settings['number']]);
    }

    public function cancelDocument(Request $request, Tenant $routeTenant, GeneratedDocument $document): JsonResponse
    {
        $tenant = $this->tenant($request);
        $this->assertSecretaryDocument($document, $tenant);
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);
        DB::transaction(function () use ($document, $tenant, $data, $request): void {
            $document = GeneratedDocument::query()->where('tenant_id', $tenant->id)->lockForUpdate()->findOrFail($document->id);
            abort_unless($this->isLetter($document) && $document->status === 'issued', 409, 'Somente ofícios emitidos podem ser cancelados.');
            $settings = $document->document_settings;
            $settings['cancellation_reason'] = $data['reason'];
            $settings['cancelled_at'] = now()->toIso8601String();
            $settings['cancelled_by'] = $request->user()->id;
            $settings['revision']++;
            $document->update(['status' => 'cancelled', 'document_settings' => $settings, 'last_edited_by' => $request->user()->id]);
            $this->letters->snapshot($document, 'cancelled', $request->user()->id);
        });

        return response()->json(['message' => 'Ofício cancelado.']);
    }

    public function previewRevision(Request $request, Tenant $routeTenant, GeneratedDocument $document, int $revision)
    {
        $tenant = $this->tenant($request);
        $this->assertSecretaryDocument($document, $tenant);
        abort_unless($this->isLetter($document), 404);
        $entry = DB::table('generated_document_revisions')->where('generated_document_id', $document->id)->where('revision', $revision)->first();
        abort_unless($entry, 404);
        if (in_array($entry->event, ['issued', 'cancelled'], true)) {
            return $this->previewDocument($request, $routeTenant, $document, app(TemplatedPdfService::class));
        }
        $snapshot = new GeneratedDocument(json_decode($entry->snapshot, true, flags: JSON_THROW_ON_ERROR));

        return $this->pdfResponse($this->letters->render($tenant, $snapshot)['bytes'], 'rascunho-revisao-'.$revision.'.pdf');
    }

    public function listImages(Request $request): JsonResponse
    {
        $tenant = $this->tenant($request);
        $images = $this->assets->images($tenant)->latest('id')->limit(60)->get(['id', 'name']);

        return response()->json($images->map(fn ($image) => ['name' => $image->name, 'url' => $this->assets->url($tenant, $image->id)]))
            ->header('Cache-Control', 'private, no-store');
    }

    public function showImage(Request $request, Tenant $routeTenant, int $image)
    {
        $tenant = $this->tenant($request);
        $image = $this->assets->images($tenant)->findOrFail($image);
        abort_unless(Storage::disk('local')->exists($image->path), 404);

        return Storage::disk('local')->response($image->path, null, [
            'Content-Type' => $image->mime_type, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store',
        ]);
    }

    public function uploadImage(Request $request): JsonResponse
    {
        $tenant = $this->tenant($request);
        $validated = $request->validate(['image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120']]);
        $image = $this->assets->upload($tenant, $validated['image'], $request->user()->id);

        return response()->json(['url' => $this->assets->url($tenant, $image->id), 'name' => $image->name], 201);
    }

    public function importMarkdown(Request $request): JsonResponse
    {
        $tenant = $this->tenant($request);
        $data = $request->validate(['markdown' => ['required', 'string', 'max:100000']]);
        $html = Str::markdown($data['markdown'], ['html_input' => 'strip', 'allow_unsafe_links' => false, 'max_nesting_level' => 20]);

        return response()->json(['html' => $this->assets->clean($tenant, $html)])->header('Cache-Control', 'private, no-store');
    }

    public function layoutFragment(Request $request, Tenant $routeTenant, PdfLayoutTemplate $layout): JsonResponse
    {
        $tenant = $this->tenant($request);
        $this->assertTenantRecord($layout, $tenant);
        abort_unless(in_array($layout->layout_type, ['header', 'footer', 'both'], true), 404);

        return response()->json(['html' => $this->assets->clean($tenant, $layout->content, previousHtml: $layout->content)])
            ->header('Cache-Control', 'private, no-store');
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
            if (Schema::hasColumn('document_templates', 'report_contents')) {
                $rules += ['report_contents' => ['nullable', 'array'], 'report_contents.*' => ['nullable', 'string', 'max:100000'],
                    'section_order' => ['nullable', 'array', 'max:30'], 'section_order.*' => ['string', 'max:60']];
                foreach (['sales_project_id' => 'sales_projects', 'customer_id' => 'customers', 'organization_id' => 'organizations'] as $field => $table) {
                    $rules[$field] = ['nullable', 'integer', Rule::exists($table, 'id')->where('tenant_id', $tenant->id)->whereNull('deleted_at')];
                }
            }
        } else {
            $rules += ['type' => ['required', Rule::in(array_keys(DocumentTemplate::TYPES))], 'content' => ['required', 'string', 'max:1500000']] + $this->letters->rules();
        }
        $validated = $request->validate($rules);
        $this->validateLayouts($tenant, $validated);

        if (($validated['type'] ?? null) === OfficialLetterService::TYPE) {
            $validated['institutional_settings'] = array_replace(OfficialLetterService::LAYOUT, $validated['layout'] ?? []);
        }
        unset($validated['layout'], $validated['letter']);

        return $validated;
    }

    private function validateDocument(Request $request, Tenant $tenant): array
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:220'], 'content' => ['required', 'string', 'max:1500000'],
            'template_id' => ['nullable', 'integer'], 'paper_size' => ['required', Rule::in(array_keys(DocumentTemplate::PAPER_SIZES))],
            'paper_orientation' => ['required', Rule::in(array_keys(DocumentTemplate::PAPER_ORIENTATIONS))],
            'header_layout_id' => ['nullable', 'integer'], 'footer_layout_id' => ['nullable', 'integer'],
            'revision' => ['nullable', 'integer', 'min:0'], 'autosave' => ['nullable', 'boolean'],
        ] + $this->letters->rules());
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
                abort_unless(PdfLayoutTemplate::query()->where('tenant_id', $tenant->id)->whereIn('layout_type', [Str::before($key, '_'), 'both'])->whereKey($data[$key])->exists(), 422, 'Layout inválido para esta organização.');
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

    private function documentSettings(array $data, bool $official, ?DocumentTemplate $template): array
    {
        $settings = collect($data)->only(['paper_size', 'paper_orientation', 'header_layout_id', 'footer_layout_id'])->all();
        if ($official) {
            $settings += ['paper_size' => $template?->paper_size ?: 'a4', 'paper_orientation' => $template?->paper_orientation ?: 'portrait',
                'header_layout_id' => $template?->header_layout_id, 'footer_layout_id' => $template?->footer_layout_id];
            $settings['letter'] = $data['letter'] ?? [];
            $settings['layout'] = array_replace(OfficialLetterService::LAYOUT, $template?->institutional_settings ?? [], $data['layout'] ?? []);
        }

        return $settings;
    }

    private function resolveTemplateContent(DocumentTemplate $template, Tenant $tenant): string
    {
        $variables = app(TemplatedPdfService::class)->resolveSystemVariables($tenant);
        $content = (string) $template->content;
        foreach ($variables as $key => $value) {
            $content = str_replace($key, (string) $value, $content);
        }

        return $this->assets->clean($tenant, $content);
    }

    private function editorView(Tenant $tenant, string $mode, ?int $recordId, array $record): View
    {
        foreach (['content', 'consent_content_before', 'consent_content'] as $htmlField) {
            if (! empty($record[$htmlField])) {
                $record[$htmlField] = $this->assets->clean($tenant, (string) $record[$htmlField], previousHtml: $recordId ? (string) $record[$htmlField] : null);
            }
        }
        $record['report_contents'] = collect($record['report_contents'] ?? [])->map(fn ($html) => $this->sanitizer->sanitize((string) $html))->all();
        $layoutIds = array_filter([$record['header_layout_id'] ?? null, $record['footer_layout_id'] ?? null]);
        $layouts = PdfLayoutTemplate::query()->where('tenant_id', $tenant->id)
            ->where(fn ($query) => $query->where('is_active', true)->orWhereIn('id', $layoutIds))
            ->orderBy('name')->get(['id', 'name', 'layout_type'])->groupBy('layout_type');
        foreach (['header', 'footer'] as $kind) {
            $layouts[$kind] = ($layouts[$kind] ?? collect())->concat($layouts['both'] ?? []);
        }
        $templates = DocumentTemplate::query()->where('tenant_id', $tenant->id)->where('template_category', 'custom')
            ->where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $definition = ! empty($record['system_template_key'])
            ? (DocumentTemplate::getSystemTemplateDefinitions()[$record['system_template_key']] ?? []) : [];

        $variables = DocumentTemplate::getAvailableVariables();
        $variables['Layout e página'] = PdfLayoutTemplate::getAvailableVariables();
        if (! empty($record['official']) || ($record['type'] ?? null) === OfficialLetterService::TYPE) {
            $variables = ['Ofício' => collect(OfficialLetterService::VARIABLES)->mapWithKeys(fn ($key) => ['{{'.$key.'}}' => $key])->all()];
        }
        $reportScopes = [];
        if (\App\Services\Accounting\BillingDossierReportService::isReportKey($record['system_template_key'] ?? null)) {
            foreach (['sales_project_id' => \App\Models\SalesProject::class, 'customer_id' => \App\Models\Customer::class, 'organization_id' => \App\Models\Organization::class] as $field => $model) {
                $column = $field === 'sales_project_id' ? 'title' : 'name';
                $reportScopes[$field] = $model::query()->where('tenant_id', $tenant->id)->orderBy($column)->pluck($column, 'id');
            }
        }

        return view('secretary.editor', compact('tenant', 'mode', 'recordId', 'record', 'layouts', 'templates', 'definition') + [
            'variables' => $variables, 'reportScopes' => $reportScopes,
            'branding' => $this->letters->branding($tenant),
            'letterLogo' => !empty($record['official']) || $mode === 'template' && ($record['template_category'] ?? 'custom') === 'custom'
                ? $this->assets->logo($tenant) : null,
            'abilities' => collect(array_keys(SecretaryAccess::PERMISSIONS))->mapWithKeys(fn ($action) => [$action => $this->access->allows(request()->user(), $tenant, $action)])->all(),
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
            'number' => $document->document_settings['number'] ?? null,
            'recipient' => data_get($document->document_settings, 'letter.recipient') ?: data_get($document->document_settings, 'letter.institution'),
            'subject' => data_get($document->document_settings, 'letter.subject'),
            'date' => data_get($document->document_settings, 'letter.date'),
            'author' => $document->generator?->name,
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
        return $this->pdfResponse($pdf->output(), $filename);
    }

    private function pdfResponse(string $bytes, string $filename, string $disposition = 'inline')
    {
        return response($bytes, 200, ['Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition.'; filename="'.$filename.'"', 'Cache-Control' => 'no-store, private']);
    }

    private function queueDriveCopy(GeneratedDocument $document): void
    {
        if (! Schema::hasTable('tenant_cloud_storage_connections') || ! TenantCloudStorageConnection::query()
            ->where('tenant_id', $document->tenant_id)->where('provider', 'google_drive')->where('status', 'active')->exists()) {
            return;
        }
        try {
            if (config('queue.default') === 'sync') {
                dispatch(new SyncOfficialLetterToDrive($document->tenant_id, $document->id))->afterResponse();
            } else {
                SyncOfficialLetterToDrive::dispatch($document->tenant_id, $document->id);
            }
        } catch (\Throwable $error) {
            report($error);
        }
    }

    private function isLetter(GeneratedDocument $document): bool
    {
        return $document->template?->type === OfficialLetterService::TYPE;
    }

    private function assertSecretaryDocument(GeneratedDocument $document, Tenant $tenant): void
    {
        $this->assertTenantRecord($document, $tenant);
        abort_unless($document->documentable_type === null, 404);
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
        $user = $request->user();
        abort_unless($user && $this->access->allows($user, $tenant), 403);
        $action = match (true) {
            $request->routeIs('secretary.templates.preview', 'secretary.layouts.fragment') => 'view',
            $request->routeIs('secretary.templates.*', 'secretary.layouts.*') => 'templates',
            $request->routeIs('secretary.documents.create', 'secretary.documents.store') => 'create',
            $request->routeIs('secretary.documents.update', 'secretary.documents.destroy') => 'edit',
            $request->routeIs('secretary.documents.issue') => 'issue',
            $request->routeIs('secretary.documents.cancel') => 'cancel',
            default => 'view',
        };
        abort_unless($action === 'view' || $this->access->allows($user, $tenant, $action), 403);
        if ($request->routeIs('secretary.images.store', 'secretary.markdown')) {
            abort_unless(collect(['create', 'edit', 'templates'])->contains(fn ($ability) => $this->access->allows($user, $tenant, $ability)), 403);
        }

        return $tenant;
    }
}
