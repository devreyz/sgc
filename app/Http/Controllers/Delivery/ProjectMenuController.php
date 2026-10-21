<?php

namespace App\Http\Controllers\Delivery;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\SalesProject;
use App\Models\Tenant;
use App\Services\Planning\MenuPlanningService;
use App\Services\Planning\MenuReportService;
use App\Services\TenantSecurityAuthorization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class ProjectMenuController extends Controller
{
    public function __construct(private MenuPlanningService $menus)
    {
        $this->middleware(['auth', 'any.role:registrador_entregas']);
    }

    private function project(Request $request): SalesProject
    {
        $tenantId = (int) session('tenant_id'); abort_unless($tenantId > 0, 403);
        $tenant = $request->route('tenant');
        if ($tenant instanceof Tenant) { abort_unless((int) $tenant->id === $tenantId, 403); }
        abort_unless(Schema::hasTable('project_menus'), 422, 'Aplique a migração de receitas e cardápios.');
        return SalesProject::where('tenant_id', $tenantId)->findOrFail((int) $request->route('project'));
    }

    public function show(Request $request)
    {
        $project = $this->project($request);
        $menu = $this->menus->menu($project, (int) $request->route('menu'));
        $tenantId = (int) $project->tenant_id;
        $menu->load(['days' => fn ($q) => $q->where('tenant_id', $tenantId)->orderBy('date'),
            'days.meals' => fn ($q) => $q->where('tenant_id', $tenantId), 'days.meals.items' => fn ($q) => $q->where('tenant_id', $tenantId)]);
        $canApprove = false;
        if ($request->user()) {
            try { app(TenantSecurityAuthorization::class)->authorize($request->user(), $tenantId, 'approve_menus'); $canApprove = true; }
            catch (\Illuminate\Auth\Access\AuthorizationException) { /* No approval permission in this tenant. */ }
        }
        return response()->view('delivery.project-menu', [
            'currentTenant' => $project->tenant, 'project' => $project, 'menu' => $menu, 'canApprove' => $canApprove,
            'products' => Product::where('tenant_id', $tenantId)->orderBy('name')->get(),
            'recipes' => Recipe::where('tenant_id', $tenantId)->orderBy('code')->orderByDesc('version')->get(),
            'report' => app(MenuReportService::class)->build($project, $menu->id),
        ])->header('Cache-Control', 'no-store, private');
    }

    public function store(Request $request)
    {
        $project = $this->project($request); $id = (int) $request->route('menu');
        $menu = match ($request->route('action')) {
            'meal' => $this->menus->saveMeal($project, $id, $request->all()),
            'remove-meal' => $this->menus->removeMeal($project, $id, $request->all()),
            'transition' => $this->menus->transition($project, $id, $request->all()),
            'clone' => $this->menus->cloneMenu($project, $id),
            default => abort(404),
        };
        if ($request->expectsJson()) { return response()->json(['id' => $menu->id, 'revision' => $menu->revision, 'status' => $menu->status])->header('Cache-Control', 'no-store, private'); }
        return redirect()->route('delivery.projects.menus.show', ['tenant' => $project->tenant->slug, 'project' => $project->id, 'menu' => $menu->id])->with('status', 'Cardápio atualizado.');
    }
}
