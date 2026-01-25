<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Equipment extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'equipment';

    protected $fillable = [
        'name',
        'code',
        'type',
        'brand',
        'model',
        'year',
        'serial_number',
        'plate',
        'current_hours',
        'current_km',
        'purchase_date',
        'purchase_value',
        'status',
        'responsible_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'current_hours' => 'decimal:2',
            'current_km' => 'decimal:2',
            'purchase_date' => 'date',
            'purchase_value' => 'decimal:2',
        ];
    }

    // Types of equipment
    public const TYPES = [
        'tractor' => 'Trator',
        'truck' => 'Caminhão',
        'harvester' => 'Colheitadeira',
        'implement' => 'Implemento',
        'vehicle' => 'Veículo',
        'pump' => 'Bomba/Motor',
        'generator' => 'Gerador',
        'other' => 'Outro',
    ];

    // Status options
    public const STATUSES = [
        'active' => 'Ativo',
        'maintenance' => 'Em Manutenção',
        'inactive' => 'Inativo',
    ];

    /**
     * Get the responsible user.
     */
    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_id');
    }

    /**
     * Get maintenance schedules.
     */
    public function maintenanceSchedules(): HasMany
    {
        return $this->hasMany(MaintenanceSchedule::class);
    }

    /**
     * Get maintenance records.
     */
    public function maintenanceRecords(): HasMany
    {
        return $this->hasMany(MaintenanceRecord::class);
    }

    /**
     * Get equipment readings.
     */
    public function readings(): HasMany
    {
        return $this->hasMany(EquipmentReading::class);
    }

    /**
     * Get overdue maintenance count.
     */
    public function getOverdueMaintenanceCountAttribute(): int
    {
        return $this->maintenanceSchedules()->where('status', 'overdue')->count();
    }

    /**
     * Get pending maintenance count.
     */
    public function getPendingMaintenanceCountAttribute(): int
    {
        return $this->maintenanceSchedules()
            ->where('status', 'pending')
            ->where(function ($q) {
                $q->where('next_hours', '<=', $this->current_hours + 50)
                  ->orWhere('next_km', '<=', $this->current_km + 500)
                  ->orWhere('next_date', '<=', now()->addDays(7));
            })
            ->count();
    }

    /**
     * Update readings and check maintenance.
     */
    public function updateReading(string $type, float $value, ?string $notes = null): void
    {
        // Create reading record
        $this->readings()->create([
            'reading_type' => $type,
            'value' => $value,
            'reading_date' => now(),
            'notes' => $notes,
            'recorded_by' => auth()->id(),
        ]);

        // Update current value
        if ($type === 'hours') {
            $this->update(['current_hours' => $value]);
        } else {
            $this->update(['current_km' => $value]);
        }

        // Check maintenance schedules
        $this->checkMaintenanceSchedules();
    }

    /**
     * Check and update maintenance schedule statuses.
     */
    public function checkMaintenanceSchedules(): void
    {
        foreach ($this->maintenanceSchedules as $schedule) {
            $isOverdue = false;

            if ($schedule->next_hours && $this->current_hours >= $schedule->next_hours) {
                $isOverdue = true;
            }

            if ($schedule->next_km && $this->current_km >= $schedule->next_km) {
                $isOverdue = true;
            }

            if ($schedule->next_date && $schedule->next_date->isPast()) {
                $isOverdue = true;
            }

            if ($isOverdue && $schedule->status !== 'overdue') {
                $schedule->update(['status' => 'overdue']);
            }
        }
    }

    /**
     * Scope active equipment.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope equipment needing attention.
     */
    public function scopeNeedsAttention($query)
    {
        return $query->whereHas('maintenanceSchedules', function ($q) {
            $q->whereIn('status', ['overdue', 'pending']);
        });
    }
}
