<?php

namespace App\Models;

use App\Enums\AssetStatus;
use App\Enums\AssetType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Asset extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'name',
        'identifier',
        'type',
        'brand',
        'model',
        'year_manufacture',
        'year_model',
        'acquisition_date',
        'acquisition_value',
        'current_value',
        'invoice_number',
        'status',
        'location',
        'renavam',
        'chassis',
        'licensing_expiry',
        'horimeter',
        'odometer',
        'last_maintenance',
        'next_maintenance',
        'document_path',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'type' => AssetType::class,
            'status' => AssetStatus::class,
            'acquisition_date' => 'date',
            'acquisition_value' => 'decimal:2',
            'current_value' => 'decimal:2',
            'licensing_expiry' => 'date',
            'last_maintenance' => 'date',
            'next_maintenance' => 'date',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'identifier', 'type', 'status', 'horimeter', 'odometer'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Get services that use this asset by default.
     */
    public function services(): HasMany
    {
        return $this->hasMany(Service::class, 'default_asset_id');
    }

    /**
     * Get service orders using this asset.
     */
    public function serviceOrders(): HasMany
    {
        return $this->hasMany(ServiceOrder::class);
    }

    /**
     * Get expenses related to this asset.
     */
    public function expenses(): MorphMany
    {
        return $this->morphMany(Expense::class, 'expenseable');
    }

    /**
     * Get documents for this asset.
     */
    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    /**
     * Get the full identification.
     */
    public function getFullNameAttribute(): string
    {
        $parts = array_filter([$this->name, $this->identifier, $this->brand, $this->model]);
        return implode(' - ', $parts);
    }

    /**
     * Calculate total expenses for this asset.
     */
    public function getTotalExpensesAttribute(): float
    {
        return $this->expenses()->sum('amount');
    }

    /**
     * Check if licensing is expired.
     */
    public function isLicensingExpired(): bool
    {
        return $this->licensing_expiry && $this->licensing_expiry->isPast();
    }

    /**
     * Check if maintenance is due.
     */
    public function isMaintenanceDue(): bool
    {
        return $this->next_maintenance && $this->next_maintenance->isPast();
    }

    /**
     * Scope to get available assets
     */
    public function scopeAvailable($query)
    {
        return $query->where('status', AssetStatus::DISPONIVEL);
    }

    /**
     * Scope to filter by type
     */
    public function scopeOfType($query, AssetType $type)
    {
        return $query->where('type', $type);
    }
}
