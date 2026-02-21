<?php

namespace App\Models;

use App\Enums\ServiceOrderPaymentStatus;
use App\Enums\ServiceOrderStatus;
use App\Traits\BelongsToTenant;
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
    use BelongsToTenant, HasFactory, LogsActivity, SoftDeletes;

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
        'actual_quantity',
        'unit',
        'unit_price',
        'total_price',
        'discount',
        'final_price',
        'provider_payment',
        'location',
        'distance_km',
        'status',
        'payment_status',
        'paid',
        'paid_date',
        'associate_payment_status',
        'associate_paid_at',
        'associate_payment_id',
        'provider_payment_status',
        'provider_paid_at',
        'provider_payment_id',
        'operator_id',
        'service_provider_id',
        'horimeter_start',
        'horimeter_end',
        'odometer_start',
        'odometer_end',
        'fuel_used',
        'work_description',
        'notes',
        'receipt_path',
        'created_by',
        'approved_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => ServiceOrderStatus::class,
            'associate_payment_status' => ServiceOrderPaymentStatus::class,
            'provider_payment_status' => ServiceOrderPaymentStatus::class,
            'scheduled_date' => 'date',
            'execution_date' => 'date',
            'paid_date' => 'date',
            'associate_paid_at' => 'datetime',
            'provider_paid_at' => 'datetime',
            'quantity' => 'decimal:2',
            'actual_quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'total_price' => 'decimal:2',
            'discount' => 'decimal:2',
            'final_price' => 'decimal:2',
            'provider_payment' => 'decimal:2',
            'distance_km' => 'decimal:2',
            'fuel_used' => 'decimal:2',
            'paid' => 'boolean',
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
                $lastOrder = static::withTrashed()->latest('id')->first();
                $nextNum = $lastOrder ? (intval(preg_replace('/\D/', '', $lastOrder->number)) + 1) : 1;
                $model->number = 'OS'.str_pad($nextNum, 6, '0', STR_PAD_LEFT);
            }

            // Defaults para campos obrigatórios no DB
            $model->total_price = $model->total_price ?? 0;
            $model->final_price = $model->final_price ?? 0;
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
     * Get expenses linked to this service order (via expenseable polymorphic).
     */
    public function expenses(): MorphMany
    {
        return $this->morphMany(Expense::class, 'expenseable');
    }

    /**
     * Get the payments for this order.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(ServiceOrderPayment::class);
    }

    public function clientPayments(): HasMany
    {
        return $this->hasMany(ServiceOrderPayment::class)->where('type', 'client');
    }

    public function providerPayments(): HasMany
    {
        return $this->hasMany(ServiceOrderPayment::class)->where('type', 'provider');
    }

    public function getTotalClientPaidAttribute(): float
    {
        return (float) $this->clientPayments()
            ->where('status', \App\Enums\ServiceOrderPaymentStatus::BILLED)
            ->sum('amount');
    }

    public function getTotalProviderPaidAttribute(): float
    {
        return (float) $this->providerPayments()
            ->where('status', \App\Enums\ServiceOrderPaymentStatus::BILLED)
            ->sum('amount');
    }

    public function getClientRemainingAttribute(): float
    {
        return max(0, (float) ($this->final_price ?? 0) - $this->total_client_paid);
    }

    public function getProviderRemainingAttribute(): float
    {
        return max(0, (float) ($this->provider_payment ?? 0) - $this->total_provider_paid);
    }

    public function isClientFullyPaid(): bool
    {
        return $this->total_client_paid >= (float) ($this->final_price ?? 0) && (float) ($this->final_price ?? 0) > 0;
    }

    public function isProviderFullyPaid(): bool
    {
        return $this->total_provider_paid >= (float) ($this->provider_payment ?? 0) && (float) ($this->provider_payment ?? 0) > 0;
    }

    public function getCooperativeProfitAttribute(): float
    {
        return (float) ($this->final_price ?? 0) - (float) ($this->provider_payment ?? 0);
    }

    /**
     * @deprecated Use total_client_paid instead
     */
    public function getTotalPaidAttribute(): float
    {
        return (float) $this->payments()->sum('amount');
    }

    /**
     * @deprecated Use client_remaining instead
     */
    public function getRemainingAmountAttribute(): float
    {
        return max(0, (float) ($this->final_price ?? 0) - $this->total_paid);
    }

    /**
     * @deprecated Use isClientFullyPaid() instead
     */
    public function isFullyPaid(): bool
    {
        return $this->total_paid >= (float) ($this->final_price ?? 0);
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
