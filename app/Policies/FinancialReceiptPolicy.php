<?php

namespace App\Policies;

use App\Models\FinancialReceipt;
use App\Models\User;

class FinancialReceiptPolicy
{
    public function viewAny(User $user): bool
    {
        return session('tenant_id') !== null && $user->can('view_any_financial::receipt');
    }

    public function view(User $user, FinancialReceipt $receipt): bool
    {
        return $this->sameTenant($receipt) && $user->can('view_financial::receipt');
    }

    public function create(User $user): bool
    {
        return session('tenant_id') !== null && $user->can('create_financial::receipt');
    }

    public function update(User $user, FinancialReceipt $receipt): bool
    {
        return $receipt->isDraft() && $this->sameTenant($receipt) && $user->can('update_financial::receipt');
    }

    public function delete(User $user, FinancialReceipt $receipt): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function forceDelete(User $user, FinancialReceipt $receipt): bool
    {
        return false;
    }

    public function restore(User $user, FinancialReceipt $receipt): bool
    {
        return false;
    }

    public function issue(User $user, FinancialReceipt $receipt): bool
    {
        return $receipt->isDraft() && $this->sameTenant($receipt) && $user->can('financial_receipt.issue');
    }

    public function cancel(User $user, FinancialReceipt $receipt): bool
    {
        return $receipt->isIssued() && $this->sameTenant($receipt) && $user->can('financial_receipt.cancel');
    }

    private function sameTenant(FinancialReceipt $receipt): bool
    {
        return session('tenant_id') !== null && (int) session('tenant_id') === (int) $receipt->tenant_id;
    }
}
