<?php

namespace App\Services\PreDistributions;

use App\Models\PreDistributionSheet;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class PreDistributionAccess
{
    public function context(User $actor, int $tenantId): bool
    {
        return config('pre_distributions.enabled') && (int) session('tenant_id') === $tenantId
            && (bool) $actor->status && DB::table('tenant_user')->where('tenant_id', $tenantId)
                ->where('user_id', $actor->id)->where('status', true)->exists();
    }

    public function manager(User $actor, int $tenantId): bool
    {
        return $this->context($actor, $tenantId) && $actor->checkPermissionTo('manage_pre_distributions');
    }

    public function view(User $actor, PreDistributionSheet $sheet): bool
    {
        return $this->context($actor, (int) $sheet->tenant_id)
            && ($this->manager($actor, (int) $sheet->tenant_id)
                || ($actor->checkPermissionTo('view_pre_distributions') && (int) $sheet->assigned_to === (int) $actor->id));
    }

    public function record(User $actor, PreDistributionSheet $sheet): bool
    {
        return $this->view($actor, $sheet) && ($this->manager($actor, (int) $sheet->tenant_id)
            || $actor->checkPermissionTo('record_pre_distributions'));
    }

    public function assertContext(User $actor, int $tenantId): void
    {
        abort_unless(config('pre_distributions.enabled'), 404);
        abort_unless($this->context($actor, $tenantId), 403, 'Tenant ou vínculo de acesso inválido.');
    }
}
