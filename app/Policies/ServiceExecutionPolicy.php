<?php

namespace App\Policies;

use App\Models\ServiceExecution;
use App\Models\User;

class ServiceExecutionPolicy
{
    public function view(User $user, ServiceExecution $execution): bool
    {
        return $this->tenant($execution) && ($user->checkPermissionTo('view_service_management') || $execution->provider?->user_id === $user->id);
    }

    public function record(User $user, ServiceExecution $execution): bool
    {
        return $this->tenant($execution) && $execution->provider?->user_id === $user->id && $user->checkPermissionTo('record_own_service_execution');
    }

    public function review(User $user, ServiceExecution $execution): bool
    {
        return $this->tenant($execution) && $user->checkPermissionTo('review_service_execution');
    }

    private function tenant(ServiceExecution $execution): bool
    {
        return (int) $execution->tenant_id === (int) session('tenant_id');
    }
}
