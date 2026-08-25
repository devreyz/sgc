<?php

namespace App\Observers;

use App\Models\Organization;
use App\Services\CustomerHierarchyService;

class OrganizationObserver
{
    public function __construct(private readonly CustomerHierarchyService $hierarchy) {}

    public function deleting(Organization $organization): void
    {
        $this->hierarchy->ensureOrganizationCanDelete($organization);
    }
}
