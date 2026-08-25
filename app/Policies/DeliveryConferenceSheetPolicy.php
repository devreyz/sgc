<?php

namespace App\Policies;

use App\Models\DeliveryConferenceSheet;
use App\Models\User;

class DeliveryConferenceSheetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_delivery_conference_sheets');
    }

    public function view(User $user, DeliveryConferenceSheet $sheet): bool
    {
        return $this->sameTenant($sheet) && $user->can('view_delivery_conference_sheets');
    }

    public function create(User $user): bool
    {
        return $user->can('create_delivery_conference_sheets');
    }

    public function update(User $user, DeliveryConferenceSheet $sheet): bool
    {
        return $this->sameTenant($sheet) && $sheet->isDraft() && $user->can('create_delivery_conference_sheets');
    }

    public function issue(User $user, DeliveryConferenceSheet $sheet): bool
    {
        return $this->sameTenant($sheet) && $user->can('issue_delivery_conference_sheets');
    }

    public function review(User $user, DeliveryConferenceSheet $sheet): bool
    {
        return $this->sameTenant($sheet) && $user->can('review_delivery_conference_sheets');
    }

    public function upload(User $user, DeliveryConferenceSheet $sheet): bool
    {
        return $this->sameTenant($sheet) && $user->can('upload_delivery_conference_documents');
    }

    public function prepareBilling(User $user, DeliveryConferenceSheet $sheet): bool
    {
        return $this->sameTenant($sheet) && $user->can('prepare_billing_from_delivery_conference');
    }

    public function cancel(User $user, DeliveryConferenceSheet $sheet): bool
    {
        return $this->sameTenant($sheet) && $user->can('cancel_delivery_conference_sheets');
    }

    public function delete(User $user, DeliveryConferenceSheet $sheet): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function forceDelete(User $user, DeliveryConferenceSheet $sheet): bool
    {
        return false;
    }

    private function sameTenant(DeliveryConferenceSheet $sheet): bool
    {
        return (int) session('tenant_id') > 0 && (int) session('tenant_id') === (int) $sheet->tenant_id;
    }
}
