<?php

namespace App\Services\Accounting;

use App\Models\AccountingAccessScope;
use App\Models\SalesProject;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

final class AccountingAccessService
{
    public function isRestricted(User $user, int $tenantId): bool
    {
        if ($user->hasRoleInTenant(['super_admin', 'admin', 'tesoureiro'], $tenantId) || $user->isTenantAdmin($tenantId)) {
            return false;
        }

        return $user->hasRoleInTenant('contador', $tenantId);
    }

    public function canManage(User $user, int $tenantId): bool
    {
        return ! $this->isRestricted($user, $tenantId)
            && ($user->hasRoleInTenant(['super_admin', 'admin', 'tesoureiro'], $tenantId) || $user->isTenantAdmin($tenantId));
    }

    public function projectIds(User $user, int $tenantId): Collection
    {
        if (! $this->isRestricted($user, $tenantId)) {
            return SalesProject::withoutGlobalScopes()->where('tenant_id', $tenantId)->whereNull('deleted_at')
                ->pluck('id')->map(fn ($id): int => (int) $id);
        }

        if (! Schema::hasTable('accounting_access_scopes')) {
            return collect();
        }

        $scopes = AccountingAccessScope::query()->where('tenant_id', $tenantId)->where('user_id', $user->id)->where('active', true)->get();
        $ids = $scopes->where('scope_type', 'project')->pluck('scope_id')->map(fn ($id): int => (int) $id);
        $types = $scopes->where('scope_type', 'project_type')->pluck('scope_value')->filter()->unique();
        if ($types->isNotEmpty()) {
            $ids = $ids->merge(SalesProject::withoutGlobalScopes()->where('tenant_id', $tenantId)
                ->whereNull('deleted_at')->whereIn('type', $types)->pluck('id'));
        }

        return $ids->map(fn ($id): int => (int) $id)->unique()->values();
    }

    public function scopeBillings(Builder $query, User $user, int $tenantId): Builder
    {
        if (! $this->isRestricted($user, $tenantId)) {
            return $query;
        }

        if (! Schema::hasTable('accounting_access_scopes')) {
            return $query->whereRaw('1 = 0');
        }

        $projectIds = $this->projectIds($user, $tenantId);
        $receiptIds = AccountingAccessScope::query()->where('tenant_id', $tenantId)->where('user_id', $user->id)
            ->where('active', true)->where('scope_type', 'customer_billing_receipt')->pluck('scope_id');

        return $query->where(function (Builder $allowed) use ($projectIds, $receiptIds): void {
            $allowed->whereIn('sales_project_id', $projectIds)->orWhereIn('id', $receiptIds);
        });
    }

    public function scopeProjects(Builder $query, User $user, int $tenantId): Builder
    {
        return $this->isRestricted($user, $tenantId)
            ? $query->whereIn('id', $this->projectIds($user, $tenantId))
            : $query;
    }

    public function associateReceiptIds(User $user, int $tenantId): Collection
    {
        if (! Schema::hasTable('accounting_access_scopes')) {
            return collect();
        }

        return AccountingAccessScope::query()->where('tenant_id', $tenantId)->where('user_id', $user->id)
            ->where('active', true)->where('scope_type', 'associate_receipt')->pluck('scope_id')
            ->map(fn ($id): int => (int) $id)->unique()->values();
    }
}
