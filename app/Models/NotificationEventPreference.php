<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationEventPreference extends Model
{
    protected $fillable = [
        'tenant_id',
        'event_key',
        'database_enabled',
        'push_enabled',
        'priority',
        'recipient_roles',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'database_enabled' => 'boolean',
            'push_enabled' => 'boolean',
            'recipient_roles' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
