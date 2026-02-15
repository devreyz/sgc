<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaintenanceType extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'name',
        'description',
        'interval_type',
        'interval_value',
        'estimated_cost',
        'warning_before',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'estimated_cost' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    // Interval types
    public const INTERVAL_TYPES = [
        'hours' => 'Horas',
        'km' => 'Quilômetros',
        'days' => 'Dias',
    ];

    /**
     * Get schedules using this type.
     */
    public function schedules(): HasMany
    {
        return $this->hasMany(MaintenanceSchedule::class);
    }

    /**
     * Get maintenance records of this type.
     */
    public function records(): HasMany
    {
        return $this->hasMany(MaintenanceRecord::class);
    }

    /**
     * Scope active types.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get interval display.
     */
    public function getIntervalDisplayAttribute(): string
    {
        $unit = match($this->interval_type) {
            'hours' => 'h',
            'km' => 'km',
            'days' => ' dias',
            default => '',
        };

        return number_format($this->interval_value, 0, ',', '.') . $unit;
    }
}
