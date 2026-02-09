<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DirectPurchaseItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'direct_purchase_id',
        'product_id',
        'product_name',
        'quantity',
        'unit',
        'unit_price',
        'total_price',
        'received_quantity',
        'fully_received',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'total_price' => 'decimal:2',
            'received_quantity' => 'decimal:2',
            'fully_received' => 'boolean',
        ];
    }

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            // Calcular total
            if (isset($model->quantity) && isset($model->unit_price)) {
                $model->total_price = $model->quantity * $model->unit_price;
            }

            // Verificar se foi totalmente recebido
            if (isset($model->received_quantity) && isset($model->quantity)) {
                $model->fully_received = $model->received_quantity >= $model->quantity;
            }
        });
    }

    public function directPurchase(): BelongsTo
    {
        return $this->belongsTo(DirectPurchase::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
