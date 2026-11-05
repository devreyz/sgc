<?php

namespace App\Policies;

use App\Models\PreDistributionSheet;
use App\Models\User;
use App\Services\PreDistributions\PreDistributionAccess;

class PreDistributionSheetPolicy
{
    public function __construct(private PreDistributionAccess $access) {}

    public function viewAny(User $actor): bool
    {
        $tenantId = (int) session('tenant_id');

        return $this->access->context($actor, $tenantId)
            && ($this->access->manager($actor, $tenantId) || $actor->checkPermissionTo('view_pre_distributions'));
    }

    public function create(User $actor): bool
    {
        return $this->access->manager($actor, (int) session('tenant_id'));
    }

    public function view(User $actor, PreDistributionSheet $sheet): bool
    {
        return $this->access->view($actor, $sheet);
    }

    public function update(User $actor, PreDistributionSheet $sheet): bool
    {
        return $sheet->status === 'draft' && $this->access->record($actor, $sheet);
    }

    public function delete(User $actor, PreDistributionSheet $sheet): bool
    {
        return false;
    }

    public function forceDelete(User $actor, PreDistributionSheet $sheet): bool
    {
        return false;
    }
}
