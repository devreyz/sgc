<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Venda Rápida (Caixa)
 * Venda avulsa sem vínculo a projeto de venda.
 * Status só muda via ação "Confirmar" ou "Cancelar".
 */
class QuickSale extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'product_id',
        'customer_id',
        'quantity',
        'unit_price',
        'payment_method',
        'status',
        'sale_date',
        'stock_movement_id',
        'notes',
        'confirmed_by',
        'confirmed_at',
        'cancelled_by',
        'cancelled_at',
        'cancellation_reason',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'quantity'      => 'decimal:3',
            'unit_price'    => 'decimal:2',
            'total_value'   => 'decimal:2',
            'sale_date'     => 'date',
            'confirmed_at'  => 'datetime',
            'cancelled_at'  => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'quantity', 'unit_price', 'payment_method'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function stockMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class);
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isConfirmed(): bool
    {
        return $this->status === 'confirmed';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }
}
