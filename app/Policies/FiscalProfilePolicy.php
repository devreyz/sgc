<?php

namespace App\Policies;

use App\Models\FiscalProfile;
use App\Models\User;

class FiscalProfilePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_accounting_fiscal_settings');
    }

    public function create(User $user): bool
    {
        return $user->can('manage_accounting_fiscal_settings');
    }

    public function update(User $user, FiscalProfile $profile): bool
    {
        return (int) session('tenant_id') === (int) $profile->tenant_id && $user->can('manage_accounting_fiscal_settings');
    }
}
