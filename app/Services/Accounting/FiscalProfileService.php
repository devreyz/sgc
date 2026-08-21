<?php

namespace App\Services\Accounting;

use App\Models\FiscalProfile;
use App\Models\SalesProject;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class FiscalProfileService
{
    public function resolve(int $tenantId, ?int $projectId): ?FiscalProfile
    {
        if ($projectId) {
            $profile = FiscalProfile::withoutGlobalScopes()->where('tenant_id', $tenantId)
                ->where('scope_key', 'project:'.$projectId)->where('active_marker', true)->latest('version')->first();
            if ($profile) {
                return $profile;
            }
        }

        return FiscalProfile::withoutGlobalScopes()->where('tenant_id', $tenantId)
            ->where('scope_key', 'tenant')->where('active_marker', true)->latest('version')->first();
    }

    public function latest(int $tenantId, ?int $projectId): ?FiscalProfile
    {
        $scope = $projectId ? 'project:'.$projectId : 'tenant';

        return FiscalProfile::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('scope_key', $scope)->latest('version')->first();
    }

    public function save(int $tenantId, ?SalesProject $project, array $data, User $actor): FiscalProfile
    {
        return DB::transaction(function () use ($tenantId, $project, $data, $actor): FiscalProfile {
            if ($project && (int) $project->tenant_id !== $tenantId) {
                abort(404);
            }
            DB::table('tenants')->where('id', $tenantId)->lockForUpdate()->firstOrFail();
            $scope = $project ? 'project:'.$project->id : 'tenant';
            $rows = FiscalProfile::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('scope_key', $scope)->lockForUpdate()->get();
            $active = (bool) ($data['active'] ?? false);
            if ($active) {
                $rows->where('active_marker', true)->each(fn (FiscalProfile $row) => $row->forceFill(['active_marker' => null, 'status' => 'superseded'])->save());
            }
            $material = [
                'document_type' => $data['document_type'] ?? null, 'amount_source' => $data['amount_source'] ?? null,
                'require_issuer_tax_id' => (bool) ($data['require_issuer_tax_id'] ?? false),
                'require_issuer_address' => (bool) ($data['require_issuer_address'] ?? false),
                'require_recipient_tax_id' => (bool) ($data['require_recipient_tax_id'] ?? false),
                'require_xml' => (bool) ($data['require_xml'] ?? false), 'require_pdf' => (bool) ($data['require_pdf'] ?? false),
                'standard_notes' => trim((string) ($data['standard_notes'] ?? '')) ?: null,
            ];
            $profile = FiscalProfile::withoutGlobalScopes()->create($material + [
                'tenant_id' => $tenantId, 'sales_project_id' => $project?->id, 'scope_key' => $scope,
                'version' => (int) $rows->max('version') + 1, 'status' => $active ? 'active' : 'draft',
                'active_marker' => $active ? true : null, 'profile_hash' => hash('sha256', json_encode($material, JSON_THROW_ON_ERROR)),
                'created_by' => $actor->id,
            ]);
            activity()->performedOn($profile)->causedBy($actor)->withProperties([
                'tenant_id' => $tenantId, 'scope' => $scope, 'version' => $profile->version, 'status' => $profile->status,
            ])->log($active ? 'Configuração fiscal ativada' : 'Rascunho de configuração fiscal criado');

            return $profile;
        }, 5);
    }
}
