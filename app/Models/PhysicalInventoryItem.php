<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PhysicalInventoryItem extends Model
{
    protected $fillable = [
        'physical_inventory_id',
        'product_id',
        'expected_quantity',
        'actual_quantity',
        'difference',
        'adjustment_movement_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'expected_quantity' => 'decimal:3',
            'actual_quantity'   => 'decimal:3',
            'difference'        => 'decimal:3',
        ];
    }

    public function inventory(): BelongsTo
    {
        return $this->belongsTo(PhysicalInventory::class, 'physical_inventory_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function adjustmentMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class, 'adjustment_movement_id');
    }

    /**
     * Calcular e salvar a diferença.
     */
    public function computeDifference(): void
    {
        if ($this->actual_quantity !== null) {
            $this->difference = $this->actual_quantity - $this->expected_quantity;
            $this->save();
        }
    }
}
