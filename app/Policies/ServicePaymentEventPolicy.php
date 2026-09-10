<?php

namespace App\Policies;

use App\Models\ServicePaymentEvent;
use App\Models\User;

class ServicePaymentEventPolicy
{
    public function view(User $user, ServicePaymentEvent $payment): bool
    {
        return (int) $payment->tenant_id === (int) session('tenant_id') && $user->checkPermissionTo('view_service_financials');
    }

    public function reverse(User $user, ServicePaymentEvent $payment): bool
    {
        return (int) $payment->tenant_id === (int) session('tenant_id') && $user->checkPermissionTo('reverse_service_payment');
    }
}
