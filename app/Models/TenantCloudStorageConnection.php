<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantCloudStorageConnection extends Model
{
    protected $guarded = ['id', 'tenant_id'];

    protected $hidden = ['refresh_token', 'granted_scopes', 'last_error'];

    protected function casts(): array
    {
        return [
            'refresh_token' => 'encrypted',
            'granted_scopes' => 'encrypted:array',
            'last_error' => 'encrypted',
            'connected_at' => 'datetime',
            'last_sync_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function connectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'connected_by_user_id');
    }
}
