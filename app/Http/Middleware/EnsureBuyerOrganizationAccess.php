<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use App\Models\OrganizationAuthorizedEmail;
use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class EnsureBuyerOrganizationAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            return redirect('/login')->with('error', 'Voce precisa estar autenticado.');
        }

        $tenant = $request->route('tenant');
        if (is_string($tenant)) {
            $tenant = Tenant::where('slug', $tenant)->first();

            if ($tenant) {
                $request->route()->setParameter('tenant', $tenant);
                session(['tenant_id' => $tenant->id, 'tenant_slug' => $tenant->slug]);
            }
        }

        $tenantId = $tenant?->id ?? session('tenant_id');
        if (! $tenantId) {
            return redirect()->route('home')->with('error', 'Organizacao nao encontrada.');
        }

        $email = mb_strtolower($request->user()->email);

        $authorizedAccesses = OrganizationAuthorizedEmail::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->whereRaw('LOWER(email) = ?', [$email])
            ->where('active', true)
            ->with(['organization' => fn ($query) => $query->withoutGlobalScope('tenant')])
            ->get()
            ->filter(fn (OrganizationAuthorizedEmail $access): bool => (bool) $access->organization?->active);

        $access = $this->selectAccessForRoute($request, $authorizedAccesses, (int) $tenantId);
        $organization = $access?->organization;

        if (! $organization && $request->user()->isTenantAdmin((int) $tenantId)) {
            $organization = $this->selectPreviewOrganizationForRoute($request, (int) $tenantId);
        }

        if (! $organization) {
            return redirect()->route('home')->with('error', 'Seu e-mail nao esta autorizado para o portal da organizacao compradora.');
        }

        $access?->forceFill(['last_login_at' => now()])->save();
        $request->attributes->set('buyer_access', $access);
        $request->attributes->set('buyer_organization', $organization);

        return $next($request);
    }

    private function selectAccessForRoute(Request $request, $authorizedAccesses, int $tenantId): ?OrganizationAuthorizedEmail
    {
        if ($authorizedAccesses->isEmpty()) {
            return null;
        }

        $buyerRequestId = $request->route('buyerRequest');
        if ($buyerRequestId) {
            $buyerRequestId = $this->routeId($buyerRequestId);
            $organizationId = DB::table('buyer_requests')
                ->where('tenant_id', $tenantId)
                ->where('id', $buyerRequestId)
                ->value('organization_id');

            $matchedAccess = $authorizedAccesses->first(
                fn (OrganizationAuthorizedEmail $access): bool => (int) $access->organization_id === (int) $organizationId
            );

            if ($matchedAccess) {
                return $matchedAccess;
            }
        }

        $projectId = $request->route('project');
        if ($projectId) {
            $projectId = $this->routeId($projectId);
            $allowedOrganizationIds = DB::table('sales_project_organizations')
                ->join('sales_projects', 'sales_projects.id', '=', 'sales_project_organizations.sales_project_id')
                ->where('sales_projects.tenant_id', $tenantId)
                ->where('sales_project_organizations.sales_project_id', $projectId)
                ->pluck('sales_project_organizations.organization_id')
                ->map(fn ($id): int => (int) $id)
                ->all();

            $matchedAccess = $authorizedAccesses->first(
                fn (OrganizationAuthorizedEmail $access): bool => in_array((int) $access->organization_id, $allowedOrganizationIds, true)
            );

            if ($matchedAccess) {
                return $matchedAccess;
            }
        }

        return $authorizedAccesses->first();
    }

    private function selectPreviewOrganizationForRoute(Request $request, int $tenantId): ?Organization
    {
        $buyerRequestId = $request->route('buyerRequest');
        if ($buyerRequestId) {
            $organizationId = DB::table('buyer_requests')
                ->where('tenant_id', $tenantId)
                ->where('id', $this->routeId($buyerRequestId))
                ->value('organization_id');

            if ($organizationId) {
                return Organization::withoutGlobalScope('tenant')
                    ->where('tenant_id', $tenantId)
                    ->where('active', true)
                    ->find($organizationId);
            }
        }

        $projectId = $request->route('project');
        if ($projectId) {
            $organizationId = DB::table('sales_project_organizations')
                ->join('sales_projects', 'sales_projects.id', '=', 'sales_project_organizations.sales_project_id')
                ->join('organizations', 'organizations.id', '=', 'sales_project_organizations.organization_id')
                ->where('sales_projects.tenant_id', $tenantId)
                ->where('sales_project_organizations.sales_project_id', $this->routeId($projectId))
                ->where('organizations.tenant_id', $tenantId)
                ->where('organizations.active', true)
                ->orderBy('organizations.name')
                ->value('organizations.id');

            if ($organizationId) {
                return Organization::withoutGlobalScope('tenant')
                    ->where('tenant_id', $tenantId)
                    ->where('active', true)
                    ->find($organizationId);
            }
        }

        return null;
    }

    private function routeId(mixed $routeValue): int
    {
        if (is_object($routeValue) && method_exists($routeValue, 'getKey')) {
            return (int) $routeValue->getKey();
        }

        return (int) $routeValue;
    }
}
