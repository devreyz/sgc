<?php

namespace App\Policies;

use App\Models\SalesProjectType;
use App\Models\User;

class SalesProjectTypePolicy
{
    public function viewAny(User $user): bool
    {
        return session('tenant_id') !== null && $user->can('view_any_sales::project');
    }

    public function view(User $user, SalesProjectType $type): bool
    {
        return $this->sameTenant($type) && $user->can('view_sales::project');
    }

    public function create(User $user): bool
    {
        return session('tenant_id') !== null && $user->can('create_sales::project');
    }

    public function update(User $user, SalesProjectType $type): bool
    {
        return $this->sameTenant($type) && $user->can('update_sales::project');
    }

    public function delete(User $user, SalesProjectType $type): bool
    {
        return $this->sameTenant($type) && $user->can('delete_sales::project');
    }

    public function deleteAny(User $user): bool
    {
        return session('tenant_id') !== null && $user->can('delete_any_sales::project');
    }

    private function sameTenant(SalesProjectType $type): bool
    {
        return (int) session('tenant_id') === (int) $type->tenant_id;
    }
}
