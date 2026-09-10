<?php

namespace App\Policies;

use App\Models\ServiceObligation;
use App\Models\User;

class ServiceObligationPolicy
{
    public function view(User $user, ServiceObligation $obligation): bool
    {
        if (! $this->tenant($obligation)) {
            return false;
        }if ($user->checkPermissionTo('view_service_financials')) {
            return true;
        }

return $obligation->direction === 'payable' && $obligation->provider?->user_id === $user->id;
    }

    public function pay(User $user, ServiceObligation $obligation): bool
    {
        return $this->tenant($obligation) && $user->checkPermissionTo($obligation->direction === 'payable' ? 'manage_service_payables' : 'manage_service_receivables');
    }

    private function tenant(ServiceObligation $obligation): bool
    {
        return (int) $obligation->tenant_id === (int) session('tenant_id');
    }
}
