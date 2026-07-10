<?php

namespace App\Http\Resources;

use App\Services\TenantIdentityService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserBriefResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $displayName = app(TenantIdentityService::class)
            ->displayName(session('tenant_id'), (int) $this->id);

        return [
            'id' => $this->id,
            'name' => $displayName,
            'display_name' => $displayName,
            'avatar' => $this->avatar,
        ];
    }
}
