<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantCloudStorageConnection extends Model
{
    protected $guarded = ['id', 'tenant_id'];

    protected $hidden = [
        'oauth_client_id',
        'oauth_client_secret',
        'refresh_token',
        'granted_scopes',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'oauth_client_id' => 'encrypted',
            'oauth_client_secret' => 'encrypted',
            'refresh_token' => 'encrypted',
            'granted_scopes' => 'encrypted:array',
            'last_error' => 'encrypted',
            'connected_at' => 'datetime',
            'last_sync_at' => 'datetime',
        ];
    }

    public function hasOAuthConfiguration(): bool
    {
        return trim((string) $this->oauth_client_id) !== ''
            && trim((string) $this->oauth_client_secret) !== '';
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
