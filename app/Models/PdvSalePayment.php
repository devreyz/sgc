<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PdvSalePayment extends Model
{
    protected $hidden = [
        'cash_movement_id',
    ];

    protected $fillable = [
        'pdv_sale_id',
        'payment_method',
        'amount',
        'cash_movement_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(PdvSale::class, 'pdv_sale_id');
    }

    public function cashMovement(): BelongsTo
    {
        return $this->belongsTo(CashMovement::class);
    }
}
