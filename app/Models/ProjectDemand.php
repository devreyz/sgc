<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ProjectDemand extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'sales_project_id',
        'product_id',
        'customer_id',
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
     * Get the specific customer for this demand (optional).
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
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
        // Legacy estimate only. Financial totals must come from distributions.
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
            ->whereNotNull('parent_delivery_id')
            ->when($this->customer_id, fn ($query) => $query->where('customer_id', $this->customer_id))
            ->sum('quantity');
        $this->saveQuietly();
    }

    protected static function booted(): void
    {
        static::saving(function (ProjectDemand $demand) {
            $tenantId = (int) ($demand->tenant_id ?: session('tenant_id'));
            $project = SalesProject::query()
                ->whereKey($demand->sales_project_id)
                ->where('tenant_id', $tenantId)
                ->firstOrFail();
            $demand->tenant_id = $project->tenant_id;

            if (! $demand->exists || $demand->isDirty(['product_id', 'customer_id'])) {
                if ($demand->exists && $demand->deliveries()->exists()) {
                    throw ValidationException::withMessages([
                        'product_id' => 'Produto e destino nao podem ser alterados depois que a demanda possui entregas.',
                    ]);
                }

                $normalized = app(\App\Services\ProjectDemandService::class)->normalizedData($project, [
                    'product_id' => $demand->product_id,
                    'customer_id' => $demand->customer_id,
                ]);
                $demand->customer_id = $normalized['customer_id'];
                $demand->unit_price = $normalized['unit_price'];
            }

            if ($demand->exists) {
                app(\App\Services\ProjectDemandService::class)
                    ->assertQuantity($demand, (float) $demand->target_quantity);
            }

            $duplicate = static::query()
                ->where('tenant_id', $demand->tenant_id)
                ->where('sales_project_id', $demand->sales_project_id)
                ->where('product_id', $demand->product_id)
                ->when(
                    $demand->customer_id,
                    fn ($query) => $query->where('customer_id', $demand->customer_id),
                    fn ($query) => $query->whereNull('customer_id')
                )
                ->when($demand->exists, fn ($query) => $query->whereKeyNot($demand->getKey()))
                ->exists();

            if ($duplicate) {
                throw ValidationException::withMessages([
                    'product_id' => 'Ja existe uma demanda para este produto e destino.',
                ]);
            }
        });

        static::deleting(function (ProjectDemand $demand) {
            if ($demand->deliveries()->withTrashed()->exists()) {
                throw ValidationException::withMessages([
                    'product_id' => 'Esta demanda nao pode ser excluida porque possui entregas ou distribuicoes vinculadas.',
                ]);
            }
        });
    }
}
