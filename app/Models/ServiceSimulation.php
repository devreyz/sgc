<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceSimulation extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'service_version_id', 'service_provider_id', 'created_by', 'name',
        'status', 'auto_filled', 'values', 'result', 'diagnostics', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'auto_filled' => 'boolean',
            'values' => 'array',
            'result' => 'array',
            'diagnostics' => 'array',
            'expires_at' => 'datetime',
        ];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(ServiceVersion::class, 'service_version_id');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(ServiceProvider::class, 'service_provider_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
