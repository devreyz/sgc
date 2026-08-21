<?php

namespace App\Policies;

use App\Enums\ReceiptStatus;
use App\Models\AssociateReceipt;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AssociateReceiptPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return session('tenant_id') !== null
            && $user->can('view_any_associate::receipt');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, AssociateReceipt $associateReceipt): bool
    {
        return $this->sameTenant($associateReceipt)
            && $user->can('view_associate::receipt');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return session('tenant_id') !== null
            && $user->can('create_associate::receipt');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, AssociateReceipt $associateReceipt): bool
    {
        return $this->sameTenant($associateReceipt)
            && $associateReceipt->canBeOperationallyUpdated()
            && $user->can('update_associate::receipt');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, AssociateReceipt $associateReceipt): bool
    {
        return $this->sameTenant($associateReceipt)
            && $associateReceipt->status === ReceiptStatus::DRAFT
            && ! $associateReceipt->distributions()->exists()
            && $user->can('delete_associate::receipt');
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, AssociateReceipt $associateReceipt): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, AssociateReceipt $associateReceipt): bool
    {
        return false;
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, AssociateReceipt $associateReceipt): bool
    {
        return $this->sameTenant($associateReceipt)
            && $user->can('replicate_associate::receipt');
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('reorder_associate::receipt');
    }

    private function sameTenant(AssociateReceipt $receipt): bool
    {
        return session('tenant_id') !== null
            && (int) session('tenant_id') === (int) $receipt->tenant_id;
    }
}
