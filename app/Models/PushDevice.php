<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PushDevice extends Model
{
    protected $fillable = [
        'user_id',
        'platform',
        'installation_hash',
        'token_hash',
        'token',
        'session_hash',
        'device_name',
        'app_version',
        'os_version',
        'notifications_enabled',
        'failure_count',
        'bound_at',
        'last_seen_at',
        'last_used_at',
        'last_failure_at',
        'revoked_at',
    ];

    protected $hidden = ['token', 'token_hash', 'installation_hash', 'session_hash'];

    protected function casts(): array
    {
        return [
            'token' => 'encrypted',
            'notifications_enabled' => 'boolean',
            'bound_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'last_used_at' => 'datetime',
            'last_failure_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
