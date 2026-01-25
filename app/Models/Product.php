<?php

namespace App\Models;

use App\Enums\ProductType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Product extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'name',
        'sku',
        'category_id',
        'type',
        'unit',
        'cost_price',
        'sale_price',
        'current_stock',
        'min_stock',
        'max_stock',
        'description',
        'ncm',
        'perishable',
        'shelf_life_days',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'type' => ProductType::class,
            'cost_price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'current_stock' => 'decimal:3',
            'min_stock' => 'decimal:3',
            'max_stock' => 'decimal:3',
            'perishable' => 'boolean',
            'status' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'sku', 'type', 'current_stock', 'cost_price', 'sale_price', 'status'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Get the category for this product.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    /**
     * Get the project demands for this product.
     */
    public function projectDemands(): HasMany
    {
        return $this->hasMany(ProjectDemand::class);
    }

    /**
     * Get the production deliveries for this product.
     */
    public function productionDeliveries(): HasMany
    {
        return $this->hasMany(ProductionDelivery::class);
    }

    /**
     * Get the purchase items for this product.
     */
    public function purchaseItems(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    /**
     * Get the stock movements for this product.
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * Check if stock is below minimum.
     */
    public function isLowStock(): bool
    {
        return $this->current_stock <= $this->min_stock;
    }

    /**
     * Check if there's enough stock for a quantity.
     */
    public function hasStock(float $quantity): bool
    {
        return $this->current_stock >= $quantity;
    }

    /**
     * Get the full name with unit.
     */
    public function getNameWithUnitAttribute(): string
    {
        return "{$this->name} ({$this->unit})";
    }

    /**
     * Scope to get active products
     */
    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    /**
     * Scope to filter by type
     */
    public function scopeOfType($query, ProductType $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope to get low stock products
     */
    public function scopeLowStock($query)
    {
        return $query->whereColumn('current_stock', '<=', 'min_stock');
    }
}
