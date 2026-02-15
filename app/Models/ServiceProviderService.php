<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ServiceProviderService extends Model
{
    use BelongsToTenant, HasFactory, LogsActivity;

    protected $fillable = [
        'service_provider_id',
        'service_id',
        'provider_hourly_rate',
        'provider_daily_rate',
        'provider_unit_rate',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'provider_hourly_rate' => 'decimal:2',
            'provider_daily_rate' => 'decimal:2',
            'provider_unit_rate' => 'decimal:2',
            'status' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['service_provider_id', 'service_id', 'provider_hourly_rate', 'provider_daily_rate', 'status'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function serviceProvider(): BelongsTo
    {
        return $this->belongsTo(ServiceProvider::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
