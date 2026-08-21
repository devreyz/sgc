<?php

namespace App\Policies;

use App\Enums\CustomerReceiptStatus;
use App\Models\CustomerBillingReceipt;
use App\Models\User;

class CustomerBillingReceiptPolicy
{
    public function viewAny(User $user): bool
    {
        return session('tenant_id') !== null
            && $user->can('view_any_customer::billing::receipt');
    }

    public function view(User $user, CustomerBillingReceipt $receipt): bool
    {
        return $this->sameTenant($receipt)
            && $user->can('view_customer::billing::receipt');
    }

    public function create(User $user): bool
    {
        return session('tenant_id') !== null
            && $user->can('create_customer::billing::receipt');
    }

    public function update(User $user, CustomerBillingReceipt $receipt): bool
    {
        return $this->sameTenant($receipt)
            && $receipt->status === CustomerReceiptStatus::DRAFT
            && $user->can('update_customer::billing::receipt');
    }

    public function delete(User $user, CustomerBillingReceipt $receipt): bool
    {
        return $this->sameTenant($receipt)
            && $receipt->status === CustomerReceiptStatus::DRAFT
            && ! $receipt->billingDistributions()->exists()
            && $user->can('delete_customer::billing::receipt');
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function forceDelete(User $user, CustomerBillingReceipt $receipt): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, CustomerBillingReceipt $receipt): bool
    {
        return false;
    }

    public function restoreAny(User $user): bool
    {
        return false;
    }

    public function replicate(User $user, CustomerBillingReceipt $receipt): bool
    {
        return false;
    }

    public function reorder(User $user): bool
    {
        return false;
    }

    private function sameTenant(CustomerBillingReceipt $receipt): bool
    {
        return session('tenant_id') !== null
            && (int) session('tenant_id') === (int) $receipt->tenant_id;
    }
}
