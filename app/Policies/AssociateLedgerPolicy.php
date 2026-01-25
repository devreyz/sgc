<?php

namespace App\Policies;

use App\Models\AssociateLedger;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AssociateLedgerPolicy
{
    use HandlesAuthorization;

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
        return $user->can('update_associate::ledger');
    }

    public function delete(User $user, AssociateLedger $associateLedger): bool
    {
        return $user->can('delete_associate::ledger');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_associate::ledger');
    }

    public function forceDelete(User $user, AssociateLedger $associateLedger): bool
    {
        return $user->can('force_delete_associate::ledger');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_associate::ledger');
    }

    public function restore(User $user, AssociateLedger $associateLedger): bool
    {
        return $user->can('restore_associate::ledger');
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_associate::ledger');
    }

    public function replicate(User $user, AssociateLedger $associateLedger): bool
    {
        return $user->can('replicate_associate::ledger');
    }

    public function reorder(User $user): bool
    {
        return $user->can('reorder_associate::ledger');
    }
}
