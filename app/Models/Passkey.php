<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Laravel\Passkeys\Passkey as BasePasskey;

class Passkey extends BasePasskey
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'credential_id',
        'credential',
        'public_key',
        'sign_count',
        'transports',
        'aaguid',
        'backup_eligible',
        'backup_state',
        'user_verified',
        'rp_id',
        'created_ip_hash',
    ];

    protected $hidden = [
        'credential_id',
        'credential',
        'public_key',
        'created_ip_hash',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('usable', fn (Builder $query) => $query->whereNull('revoked_at'));
    }

    protected function casts(): array
    {
        return [
            'credential' => 'encrypted:array',
            'public_key' => 'encrypted',
            'transports' => 'array',
            'sign_count' => 'integer',
            'backup_eligible' => 'boolean',
            'backup_state' => 'boolean',
            'user_verified' => 'boolean',
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }
}
