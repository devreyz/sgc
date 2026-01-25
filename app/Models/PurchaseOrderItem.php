<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrderItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'purchase_order_id',
        'purchase_item_id',
        'quantity',
        'unit_price',
        'delivered_quantity',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'delivered_quantity' => 'decimal:2',
        ];
    }

    /**
     * Get the purchase order.
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /**
     * Get the purchase item.
     */
    public function purchaseItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseItem::class);
    }

    /**
     * Calculate the total price.
     */
    public function getTotalPriceAttribute(): float
    {
        return $this->quantity * $this->unit_price;
    }

    /**
     * Calculate the pending delivery quantity.
     */
    public function getPendingQuantityAttribute(): float
    {
        return max(0, $this->quantity - $this->delivered_quantity);
    }

    /**
     * Check if fully delivered.
     */
    public function isFullyDelivered(): bool
    {
        return $this->delivered_quantity >= $this->quantity;
    }
}
