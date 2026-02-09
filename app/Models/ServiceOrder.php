<?php

namespace App\Models;

use App\Enums\ServiceOrderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ServiceOrder extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'number',
        'associate_id',
        'service_id',
        'asset_id',
        'scheduled_date',
        'execution_date',
        'start_time',
        'end_time',
        'quantity',
        'unit',
        'unit_price',
        'total_price',
        'discount',
        'final_price',
        'location',
        'distance_km',
        'status',
        'operator_id',
        'service_provider_id',
        'horimeter_start',
        'horimeter_end',
        'odometer_start',
        'odometer_end',
        'fuel_used',
        'work_description',
        'notes',
        'created_by',
        'approved_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => ServiceOrderStatus::class,
            'scheduled_date' => 'date',
            'execution_date' => 'date',
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'total_price' => 'decimal:2',
            'discount' => 'decimal:2',
            'final_price' => 'decimal:2',
            'distance_km' => 'decimal:2',
            'fuel_used' => 'decimal:2',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['number', 'status', 'quantity', 'total_price', 'final_price'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->number)) {
                $model->number = 'OS-'.date('Y').'-'.str_pad(
                    static::whereYear('created_at', date('Y'))->count() + 1,
                    5,
                    '0',
                    STR_PAD_LEFT
                );
            }
        });
        
        static::saving(function ($model) {
            // Garantir que total_price exista antes do insert (DB exige valor)
            // total_price = quantity * unit_price
            if (isset($model->quantity) && isset($model->unit_price)) {
                $model->total_price = $model->quantity * $model->unit_price;
            }

            // final_price pode já vir calculado pela UI, senão aplicar desconto
            if (! isset($model->final_price)) {
                $discount = $model->discount ?? 0;
                $model->final_price = ($model->total_price ?? 0) - $discount;
            }
        });
    }

    /**
     * Get the associate.
     */
    public function associate(): BelongsTo
    {
        return $this->belongsTo(Associate::class);
    }

    /**
     * Get the service.
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * Get the asset used.
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /**
     * Backwards-compatible alias to `asset()` for views referencing `equipment`.
     */
    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'asset_id');
    }

    /**
     * Get related service provider works for this order.
     */
    public function works(): HasMany
    {
        return $this->hasMany(ServiceProviderWork::class, 'service_order_id');
    }

    /**
     * Get the operator.
     */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    /**
     * Get the service provider (external).
     */
    public function serviceProvider(): BelongsTo
    {
        return $this->belongsTo(ServiceProvider::class);
    }

    /**
     * Get the user who created this order.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who approved this order.
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the ledger entries for this order.
     */
    public function ledgerEntries(): MorphMany
    {
        return $this->morphMany(AssociateLedger::class, 'reference');
    }

    /**
     * Calculate hours worked from horimeter.
     */
    public function getHoursWorkedAttribute(): ?float
    {
        if ($this->horimeter_start && $this->horimeter_end) {
            return $this->horimeter_end - $this->horimeter_start;
        }

        return null;
    }

    /**
     * Calculate distance from odometer.
     */
    public function getDistanceTraveledAttribute(): ?int
    {
        if ($this->odometer_start && $this->odometer_end) {
            return $this->odometer_end - $this->odometer_start;
        }

        return null;
    }

    /**
     * Check if order is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === ServiceOrderStatus::COMPLETED;
    }

    /**
     * Check if order is billed.
     */
    public function isBilled(): bool
    {
        return $this->status === ServiceOrderStatus::BILLED;
    }

    /**
     * Scope to get pending orders
     */
    public function scopePending($query)
    {
        return $query->whereIn('status', [
            ServiceOrderStatus::SCHEDULED,
            ServiceOrderStatus::IN_PROGRESS,
        ]);
    }

    /**
     * Scope to filter by associate
     */
    public function scopeForAssociate($query, int $associateId)
    {
        return $query->where('associate_id', $associateId);
    }

    /**
     * Scope to filter by date range
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('execution_date', [$startDate, $endDate]);
    }
}
