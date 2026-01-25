<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'collective_purchase_id',
        'product_id',
        'product_description',
        'brand',
        'unit',
        'unit_price',
        'min_quantity',
        'max_quantity',
        'total_ordered',
        'total_received',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'min_quantity' => 'decimal:2',
            'max_quantity' => 'decimal:2',
            'total_ordered' => 'decimal:2',
            'total_received' => 'decimal:2',
        ];
    }

    /**
     * Get the collective purchase.
     */
    public function collectivePurchase(): BelongsTo
    {
        return $this->belongsTo(CollectivePurchase::class);
    }

    /**
     * Get the product.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the order items for this purchase item.
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    /**
     * Get the full product description.
     */
    public function getFullDescriptionAttribute(): string
    {
        $parts = array_filter([
            $this->product?->name,
            $this->product_description,
            $this->brand,
        ]);

        return implode(' - ', $parts);
    }

    /**
     * Calculate remaining available quantity.
     */
    public function getRemainingQuantityAttribute(): ?float
    {
        if (!$this->max_quantity) {
            return null;
        }
        
        return max(0, $this->max_quantity - $this->total_ordered);
    }

    /**
     * Update total ordered from order items.
     */
    public function updateTotalOrdered(): void
    {
        $this->total_ordered = $this->orderItems()->sum('quantity');
        $this->save();
    }
}
