<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PriceTableItem extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'price_table_id',
        'product_id',
        'sale_price',
        'cost_price',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'sale_price'  => 'decimal:4',
            'cost_price'  => 'decimal:4',
        ];
    }

    public function priceTable(): BelongsTo
    {
        return $this->belongsTo(PriceTable::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
