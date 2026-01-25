<?php

namespace App\Models;

use App\Enums\CollectivePurchaseStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class CollectivePurchase extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'title',
        'code',
        'description',
        'order_start_date',
        'order_end_date',
        'expected_delivery_date',
        'actual_delivery_date',
        'supplier_id',
        'total_value',
        'discount_percentage',
        'status',
        'invoice_number',
        'document_path',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => CollectivePurchaseStatus::class,
            'order_start_date' => 'date',
            'order_end_date' => 'date',
            'expected_delivery_date' => 'date',
            'actual_delivery_date' => 'date',
            'total_value' => 'decimal:2',
            'discount_percentage' => 'decimal:2',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'status', 'total_value', 'discount_percentage'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Get the supplier.
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Get the items available for purchase.
     */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    /**
     * Get the orders from associates.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    /**
     * Get the documents for this purchase.
     */
    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    /**
     * Get the user who created this purchase.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Calculate the total ordered value.
     */
    public function getTotalOrderedValueAttribute(): float
    {
        return $this->orders()->sum('total_value');
    }

    /**
     * Get the number of associates who ordered.
     */
    public function getTotalAssociatesAttribute(): int
    {
        return $this->orders()->distinct('associate_id')->count('associate_id');
    }

    /**
     * Check if orders are still open.
     */
    public function isOpen(): bool
    {
        return $this->status === CollectivePurchaseStatus::OPEN;
    }

    /**
     * Scope to get open purchases
     */
    public function scopeOpen($query)
    {
        return $query->where('status', CollectivePurchaseStatus::OPEN);
    }

    /**
     * Scope to filter by status
     */
    public function scopeOfStatus($query, CollectivePurchaseStatus $status)
    {
        return $query->where('status', $status);
    }
}
