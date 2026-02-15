<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EquipmentReading extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'equipment_id',
        'reading_type',
        'value',
        'reading_date',
        'notes',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'reading_date' => 'date',
        ];
    }

    // Reading types
    public const TYPES = [
        'hours' => 'Horímetro',
        'km' => 'Odômetro',
    ];

    /**
     * Get the equipment.
     */
    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    /**
     * Get the user who recorded.
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
