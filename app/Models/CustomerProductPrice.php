<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerProductPrice extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'customer_id',
        'product_id',
        'project_id',
        'sale_price',
        'cost_price',
        'start_date',
        'end_date',
    ];

    protected function casts(): array
    {
        return [
            'sale_price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(SalesProject::class, 'project_id');
    }

    /**
     * Scope: preços ativos (dentro da vigência).
     */
    public function scopeActive($query)
    {
        return $query
            ->where(fn ($q) => $q->whereNull('start_date')->orWhereDate('start_date', '<=', now()))
            ->where(fn ($q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', now()));
    }

    /**
     * Scope: filtrar por cliente e produto.
     */
    public function scopeForCustomerProduct($query, int $customerId, int $productId)
    {
        return $query->where('customer_id', $customerId)->where('product_id', $productId);
    }
}
