<?php

namespace App\Services;

use App\Models\Associate;
use App\Models\TenantUser;
use Illuminate\Support\Collection;

class TenantIdentityService
{
    private array $nameCache = [];

    public function displayName(?int $tenantId, ?int $userId): string
    {
        if (! $tenantId || ! $userId) {
            return 'Membro nao identificado';
        }

        $key = $tenantId . ':' . $userId;
        if (array_key_exists($key, $this->nameCache)) {
            return $this->nameCache[$key];
        }

        $member = TenantUser::query()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->first(['tenant_id', 'user_id', 'tenant_name']);

        return $this->nameCache[$key] = $this->nameFromMember($member);
    }

    public function namesForUsers(int $tenantId, iterable $userIds): array
    {
        $ids = collect($userIds)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $missing = $ids
            ->reject(fn (int $userId) => array_key_exists($tenantId . ':' . $userId, $this->nameCache))
            ->values();

        if ($missing->isNotEmpty()) {
            TenantUser::query()
                ->where('tenant_id', $tenantId)
                ->whereIn('user_id', $missing)
                ->get(['tenant_id', 'user_id', 'tenant_name'])
                ->each(function (TenantUser $member) {
                    $this->nameCache[$member->tenant_id . ':' . $member->user_id] = $this->nameFromMember($member);
                });

            foreach ($missing as $userId) {
                $key = $tenantId . ':' . $userId;
                $this->nameCache[$key] ??= 'Membro nao identificado';
            }
        }

        return $ids
            ->mapWithKeys(fn (int $userId) => [$userId => $this->nameCache[$tenantId . ':' . $userId]])
            ->all();
    }

    public function displayNameForAssociate(?Associate $associate): string
    {
        if (! $associate) {
            return 'Associado nao identificado';
        }

        return $this->displayName((int) $associate->tenant_id, (int) $associate->user_id);
    }

    public function namesForAssociates(Collection $associates): array
    {
        return $associates
            ->groupBy('tenant_id')
            ->flatMap(function (Collection $group, int|string $tenantId) {
                return $this->namesForUsers((int) $tenantId, $group->pluck('user_id'));
            })
            ->all();
    }

    private function nameFromMember(?TenantUser $member): string
    {
        if (! $member) {
            return 'Membro nao identificado';
        }

        $tenantName = trim((string) $member->tenant_name);

        return $tenantName !== '' ? $tenantName : 'Membro sem nome cadastrado';
    }
}
