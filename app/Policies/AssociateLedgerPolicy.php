<?php

namespace App\Policies;

use App\Models\AssociateLedger;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AssociateLedgerPolicy
{
    use HandlesAuthorization;

    /**
     * Check if model belongs to current tenant
     */
    protected function belongsToTenant(User $user, $model): bool
    {
        // Super admin bypasses tenant check
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Check if model has tenant_id and it matches current tenant
        if (isset($model->tenant_id)) {
            return $model->tenant_id === session('tenant_id');
        }

        return true;
    }

    public function viewAny(User $user): bool
    {
        // Associates can view their own ledger entries
        if ($user->associate) {
            return true;
        }
        
        return $user->can('view_any_associate::ledger');
    }

    public function view(User $user, AssociateLedger $associateLedger): bool
    {
        // Check tenant first
        if (!$this->belongsToTenant($user, $associateLedger)) {
            return false;
        }

        // Associates can only view their own ledger entries
        if ($user->associate && $user->associate->id === $associateLedger->associate_id) {
            return true;
        }
        
        return $user->can('view_associate::ledger');
    }

    public function create(User $user): bool
    {
        return $user->can('create_associate::ledger');
    }

    public function update(User $user, AssociateLedger $associateLedger): bool
    {
        return $this->belongsToTenant($user, $associateLedger)
            && $user->can('update_associate::ledger');
    }

    public function delete(User $user, AssociateLedger $associateLedger): bool
    {
        return $this->belongsToTenant($user, $associateLedger)
            && $user->can('delete_associate::ledger');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_associate::ledger');
    }

    public function forceDelete(User $user, AssociateLedger $associateLedger): bool
    {
        return $this->belongsToTenant($user, $associateLedger)
            && $user->can('force_delete_associate::ledger');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_associate::ledger');
    }

    public function restore(User $user, AssociateLedger $associateLedger): bool
    {
        return $this->belongsToTenant($user, $associateLedger)
            && $user->can('restore_associate::ledger');
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_associate::ledger');
    }

    public function replicate(User $user, AssociateLedger $associateLedger): bool
    {
        return $this->belongsToTenant($user, $associateLedger)
            && $user->can('replicate_associate::ledger');
    }

    public function reorder(User $user): bool
    {
        return $user->can('reorder_associate::ledger');
    }
}
