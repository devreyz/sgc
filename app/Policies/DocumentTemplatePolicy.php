<?php

namespace App\Policies;

use App\Models\User;
use App\Models\DocumentTemplate;
use Illuminate\Auth\Access\HandlesAuthorization;

class DocumentTemplatePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_system::pdf');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, DocumentTemplate $documentTemplate): bool
    {
        return $this->sameTenant($documentTemplate) && $user->can('view_system::pdf');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_system::pdf');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, DocumentTemplate $documentTemplate): bool
    {
        return $this->sameTenant($documentTemplate) && $user->can('update_system::pdf');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, DocumentTemplate $documentTemplate): bool
    {
        return $this->sameTenant($documentTemplate) && $user->can('delete_system::pdf');
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_system::pdf');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, DocumentTemplate $documentTemplate): bool
    {
        return $this->sameTenant($documentTemplate) && $user->can('force_delete_system::pdf');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_system::pdf');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, DocumentTemplate $documentTemplate): bool
    {
        return $this->sameTenant($documentTemplate) && $user->can('restore_system::pdf');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_system::pdf');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, DocumentTemplate $documentTemplate): bool
    {
        return $user->can('replicate_system::pdf');
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('reorder_system::pdf');
    }

    private function sameTenant(DocumentTemplate $documentTemplate): bool
    {
        return (int) session('tenant_id') === (int) $documentTemplate->tenant_id;
    }
}
