<?php

namespace App\Http\Controllers\Secretary;

use App\Http\Controllers\Controller;
use App\Models\DocumentTemplate;
use App\Models\GeneratedDocument;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SecretaryPortalController extends Controller
{
    public function __construct()
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
            'kind' => ['nullable', 'in:all,documents,templates'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $search = trim((string) ($validated['search'] ?? ''));
        $kind = $validated['kind'] ?? 'all';

        $templates = collect();
        if ($kind !== 'documents') {
            $templates = DocumentTemplate::query()
                ->where('tenant_id', $tenant->id)
                ->where('template_category', 'custom')
                ->where('is_active', true)
                ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                }))
                ->latest('updated_at')
                ->limit(40)
                ->get(['id', 'name', 'type', 'description', 'updated_at'])
                ->map(fn (DocumentTemplate $template): array => [
                    'id' => (int) $template->id,
                    'name' => $template->name,
                    'type' => DocumentTemplate::TYPES[$template->type] ?? 'Documento',
                    'description' => $template->description,
                    'updated_at' => $template->updated_at?->format('d/m/Y'),
                ]);
        }

        $documents = null;
        if ($kind !== 'templates') {
            $documents = GeneratedDocument::query()
                ->where('tenant_id', $tenant->id)
                ->with('template:id,tenant_id,name,type')
                ->when($search !== '', fn ($query) => $query->where('title', 'like', "%{$search}%"))
                ->latest('updated_at')
                ->paginate(12, ['id', 'tenant_id', 'template_id', 'title', 'signed_at', 'updated_at'])
                ->through(fn (GeneratedDocument $document): array => [
                    'id' => (int) $document->id,
                    'title' => $document->title,
                    'template' => $document->template?->name,
                    'type' => DocumentTemplate::TYPES[$document->template?->type] ?? 'Documento',
                    'signed' => $document->signed_at !== null,
                    'updated_at' => $document->updated_at?->format('d/m/Y H:i'),
                ]);
        }

        return response()->json([
            'templates' => $templates,
            'documents' => $documents,
            'summary' => [
                'templates' => DocumentTemplate::query()
                    ->where('tenant_id', $tenant->id)
                    ->where('template_category', 'custom')
                    ->where('is_active', true)
                    ->count(),
                'documents' => GeneratedDocument::query()->where('tenant_id', $tenant->id)->count(),
                'signed' => GeneratedDocument::query()
                    ->where('tenant_id', $tenant->id)
                    ->whereNotNull('signed_at')
                    ->count(),
            ],
        ])->header('Cache-Control', 'no-store, private');
    }

    private function tenant(Request $request): Tenant
    {
        $tenant = $request->route('tenant');
        abort_unless($tenant instanceof Tenant, 404);
        abort_unless((int) session('tenant_id') === (int) $tenant->id, 403);
        abort_unless($request->user()?->tenants()
            ->where('tenants.id', $tenant->id)
            ->wherePivot('status', true)
            ->exists(), 403);

        return $tenant;
    }
}
