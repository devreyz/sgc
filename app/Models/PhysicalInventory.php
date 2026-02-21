<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Inventário Físico
 *
 * Fluxo de status:
 *   draft → counting → adjusting → completed
 *   (qualquer) → cancelled
 */
class PhysicalInventory extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'description',
        'inventory_date',
        'status',
        'notes',
        'created_by',
        'completed_by',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'inventory_date' => 'date',
            'completed_at'   => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'description'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function items(): HasMany
    {
        return $this->hasMany(PhysicalInventoryItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function isDraft(): bool     { return $this->status === 'draft'; }
    public function isCounting(): bool  { return $this->status === 'counting'; }
    public function isAdjusting(): bool { return $this->status === 'adjusting'; }
    public function isCompleted(): bool { return $this->status === 'completed'; }
    public function isCancelled(): bool { return $this->status === 'cancelled'; }
}
