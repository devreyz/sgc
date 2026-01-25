<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'equipment_id',
        'maintenance_type_id',
        'last_hours',
        'last_km',
        'last_date',
        'next_hours',
        'next_km',
        'next_date',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'last_hours' => 'decimal:2',
            'last_km' => 'decimal:2',
            'last_date' => 'date',
            'next_hours' => 'decimal:2',
            'next_km' => 'decimal:2',
            'next_date' => 'date',
        ];
    }

    // Status options
    public const STATUSES = [
        'pending' => 'Pendente',
        'overdue' => 'Vencida',
        'completed' => 'Realizada',
    ];

    /**
     * Get the equipment.
     */
    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    /**
     * Get the maintenance type.
     */
    public function maintenanceType(): BelongsTo
    {
        return $this->belongsTo(MaintenanceType::class);
    }

    /**
     * Mark as completed and calculate next.
     */
    public function markCompleted(float $hours = null, float $km = null): void
    {
        $type = $this->maintenanceType;
        $equipment = $this->equipment;

        $currentHours = $hours ?? $equipment->current_hours;
        $currentKm = $km ?? $equipment->current_km;

        $updateData = [
            'last_hours' => $currentHours,
            'last_km' => $currentKm,
            'last_date' => now(),
            'status' => 'pending',
        ];

        // Calculate next based on interval type
        if ($type->interval_type === 'hours') {
            $updateData['next_hours'] = $currentHours + $type->interval_value;
        } elseif ($type->interval_type === 'km') {
            $updateData['next_km'] = $currentKm + $type->interval_value;
        } elseif ($type->interval_type === 'days') {
            $updateData['next_date'] = now()->addDays($type->interval_value);
        }

        $this->update($updateData);
    }

    /**
     * Get remaining value until next maintenance.
     */
    public function getRemainingAttribute(): ?string
    {
        $equipment = $this->equipment;

        if ($this->next_hours) {
            $remaining = $this->next_hours - $equipment->current_hours;
            return number_format(max(0, $remaining), 0, ',', '.') . 'h';
        }

        if ($this->next_km) {
            $remaining = $this->next_km - $equipment->current_km;
            return number_format(max(0, $remaining), 0, ',', '.') . 'km';
        }

        if ($this->next_date) {
            $days = now()->diffInDays($this->next_date, false);
            return $days . ' dias';
        }

        return null;
    }

    /**
     * Check if needs warning.
     */
    public function getNeedsWarningAttribute(): bool
    {
        $type = $this->maintenanceType;
        $equipment = $this->equipment;
        $warning = $type->warning_before ?? 50;

        if ($this->next_hours) {
            return ($this->next_hours - $equipment->current_hours) <= $warning;
        }

        if ($this->next_km) {
            $warningKm = $warning * 10; // 50h = 500km approx
            return ($this->next_km - $equipment->current_km) <= $warningKm;
        }

        if ($this->next_date) {
            return $this->next_date->diffInDays(now()) <= 7;
        }

        return false;
    }

    /**
     * Scope overdue.
     */
    public function scopeOverdue($query)
    {
        return $query->where('status', 'overdue');
    }

    /**
     * Scope needing attention.
     */
    public function scopeNeedsAttention($query)
    {
        return $query->whereIn('status', ['overdue', 'pending'])
            ->where(function ($q) {
                $q->whereRaw('next_hours <= (SELECT current_hours FROM equipment WHERE equipment.id = maintenance_schedules.equipment_id) + 50')
                  ->orWhereRaw('next_km <= (SELECT current_km FROM equipment WHERE equipment.id = maintenance_schedules.equipment_id) + 500')
                  ->orWhere('next_date', '<=', now()->addDays(7));
            });
    }
}
