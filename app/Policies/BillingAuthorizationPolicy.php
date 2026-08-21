<?php

namespace App\Policies;

use App\Models\BillingAuthorization;
use App\Models\CustomerBillingReceipt;
use App\Models\User;

class BillingAuthorizationPolicy
{
    public function view(User $user, BillingAuthorization $authorization): bool
    {
        return $this->sameTenant($authorization->tenant_id)
            && $user->can('view_accounting_processes');
    }

    public function send(User $user, CustomerBillingReceipt $receipt): bool
    {
        return $this->sameTenant($receipt->tenant_id)
            && $user->can('send_accounting_authorizations');
    }

    public function cancel(User $user, BillingAuthorization $authorization): bool
    {
        return $this->sameTenant($authorization->tenant_id)
            && $user->can('cancel_accounting_authorizations');
    }

    private function sameTenant(int $tenantId): bool
    {
        return (int) session('tenant_id') > 0
            && (int) session('tenant_id') === $tenantId;
    }
}
