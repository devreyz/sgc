<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ProjectDemand extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'sales_project_id',
        'product_id',
        'target_quantity',
        'delivered_quantity',
        'unit_price',
        'delivery_start',
        'delivery_end',
        'frequency',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'target_quantity' => 'decimal:3',
            'delivered_quantity' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'delivery_start' => 'date',
            'delivery_end' => 'date',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['target_quantity', 'delivered_quantity', 'unit_price'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Get the sales project.
     */
    public function salesProject(): BelongsTo
    {
        return $this->belongsTo(SalesProject::class);
    }

    /**
     * Get the product.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the deliveries for this demand.
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(ProductionDelivery::class);
    }

    /**
     * Calculate the total value.
     */
    public function getTotalValueAttribute(): float
    {
        return $this->target_quantity * $this->unit_price;
    }

    /**
     * Calculate the remaining quantity.
     */
    public function getRemainingQuantityAttribute(): float
    {
        return max(0, $this->target_quantity - $this->delivered_quantity);
    }

    /**
     * Calculate the progress percentage.
     */
    public function getProgressPercentageAttribute(): float
    {
        if ($this->target_quantity <= 0) {
            return 0;
        }
        
        return min(100, ($this->delivered_quantity / $this->target_quantity) * 100);
    }

    /**
     * Check if demand is fulfilled.
     */
    public function isFulfilled(): bool
    {
        return $this->delivered_quantity >= $this->target_quantity;
    }

    /**
     * Update delivered quantity from deliveries.
     */
    public function updateDeliveredQuantity(): void
    {
        $this->delivered_quantity = $this->deliveries()
            ->where('status', \App\Enums\DeliveryStatus::APPROVED->value)
            ->sum('quantity');
        $this->saveQuietly(); // Save without triggering events
    }
}
