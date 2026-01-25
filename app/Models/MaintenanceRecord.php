<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MaintenanceRecord extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'equipment_id',
        'maintenance_type_id',
        'title',
        'description',
        'performed_date',
        'hours_at_maintenance',
        'km_at_maintenance',
        'cost',
        'performed_by',
        'invoice_number',
        'parts_replaced',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'performed_date' => 'date',
            'hours_at_maintenance' => 'decimal:2',
            'km_at_maintenance' => 'decimal:2',
            'cost' => 'decimal:2',
            'parts_replaced' => 'array',
        ];
    }

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
     * Get the creator.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Boot model.
     */
    protected static function booted(): void
    {
        static::created(function (MaintenanceRecord $record) {
            // If linked to a maintenance type, update the schedule
            if ($record->maintenance_type_id) {
                $schedule = MaintenanceSchedule::where('equipment_id', $record->equipment_id)
                    ->where('maintenance_type_id', $record->maintenance_type_id)
                    ->first();

                if ($schedule) {
                    $schedule->markCompleted(
                        $record->hours_at_maintenance,
                        $record->km_at_maintenance
                    );
                }
            }

            // Update equipment current hours/km if provided
            $equipment = $record->equipment;
            $updateData = [];

            if ($record->hours_at_maintenance && $record->hours_at_maintenance > $equipment->current_hours) {
                $updateData['current_hours'] = $record->hours_at_maintenance;
            }

            if ($record->km_at_maintenance && $record->km_at_maintenance > $equipment->current_km) {
                $updateData['current_km'] = $record->km_at_maintenance;
            }

            if (!empty($updateData)) {
                $equipment->update($updateData);
            }
        });
    }
}
