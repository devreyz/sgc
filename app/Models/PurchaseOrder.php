<?php

namespace App\Models;

use App\Enums\PurchaseOrderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class PurchaseOrder extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'collective_purchase_id',
        'associate_id',
        'total_value',
        'status',
        'payment_method',
        'order_date',
        'delivery_date',
        'notes',
        'delivered_by',
        'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => PurchaseOrderStatus::class,
            'total_value' => 'decimal:2',
            'order_date' => 'date',
            'delivery_date' => 'date',
            'delivered_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'total_value', 'payment_method', 'delivery_date'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Get the collective purchase.
     */
    public function collectivePurchase(): BelongsTo
    {
        return $this->belongsTo(CollectivePurchase::class);
    }

    /**
     * Get the associate who made the order.
     */
    public function associate(): BelongsTo
    {
        return $this->belongsTo(Associate::class);
    }

    /**
     * Get the order items.
     */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    /**
     * Get the ledger entries for this order.
     */
    public function ledgerEntries(): MorphMany
    {
        return $this->morphMany(AssociateLedger::class, 'reference');
    }

    /**
     * Get the user who delivered this order.
     */
    public function deliverer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delivered_by');
    }

    /**
     * Update total value from items.
     */
    public function updateTotalValue(): void
    {
        $this->total_value = $this->items()->sum(\DB::raw('quantity * unit_price'));
        $this->save();
    }

    /**
     * Check if order is delivered.
     */
    public function isDelivered(): bool
    {
        return $this->status === PurchaseOrderStatus::DELIVERED;
    }

    /**
     * Scope to get pending orders
     */
    public function scopePending($query)
    {
        return $query->whereNotIn('status', [
            PurchaseOrderStatus::DELIVERED,
            PurchaseOrderStatus::CANCELLED,
        ]);
    }

    /**
     * Scope to filter by associate
     */
    public function scopeForAssociate($query, int $associateId)
    {
        return $query->where('associate_id', $associateId);
    }
}
