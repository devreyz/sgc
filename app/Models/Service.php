<?php

namespace App\Models;

use App\Enums\ServiceType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Service extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'name',
        'code',
        'description',
        'type',
        'unit',
        'base_price',
        'associate_price',
        'non_associate_price',
        'provider_hourly_rate',
        'provider_daily_rate',
        'min_charge',
        'default_asset_id',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'type' => ServiceType::class,
            'base_price' => 'decimal:2',
            'associate_price' => 'decimal:2',
            'non_associate_price' => 'decimal:2',
            'provider_hourly_rate' => 'decimal:2',
            'provider_daily_rate' => 'decimal:2',
            'min_charge' => 'decimal:2',
            'status' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'code', 'type', 'base_price', 'status'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Get the default asset for this service.
     */
    public function defaultAsset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'default_asset_id');
    }

    /**
     * Get the service providers that offer this service.
     */
    public function serviceProviders(): BelongsToMany
    {
        return $this->belongsToMany(ServiceProvider::class, 'service_provider_services')
            ->withPivot(['provider_hourly_rate', 'provider_daily_rate', 'provider_unit_rate', 'status', 'notes'])
            ->withTimestamps()
            ->wherePivot('status', true);
    }

    /**
     * Get the service orders for this service.
     */
    public function serviceOrders(): HasMany
    {
        return $this->hasMany(ServiceOrder::class);
    }

    /**
     * Get the full name with unit.
     */
    public function getNameWithUnitAttribute(): string
    {
        return "{$this->name} (R\$ {$this->base_price}/{$this->unit})";
    }

    /**
     * Scope to get active services
     */
    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    /**
     * Scope to filter by type
     */
    public function scopeOfType($query, ServiceType $type)
    {
        return $query->where('type', $type);
    }
}
