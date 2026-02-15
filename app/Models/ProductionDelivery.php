<?php

namespace App\Models;

use App\Enums\DeliveryStatus;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ProductionDelivery extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'sales_project_id',
        'project_demand_id',
        'associate_id',
        'product_id',
        'delivery_date',
        'quantity',
        'unit_price',
        'admin_fee_amount',
        'net_value',
        'status',
        'quality_grade',
        'quality_notes',
        'received_by',
        'approved_by',
        'approved_at',
        'notes',
        'paid',
        'paid_date',
        'project_payment_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => DeliveryStatus::class,
            'delivery_date' => 'date',
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'admin_fee_amount' => 'decimal:2',
            'net_value' => 'decimal:2',
            'approved_at' => 'datetime',
            'paid' => 'boolean',
            'paid_date' => 'date',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['quantity', 'unit_price', 'status', 'admin_fee_amount', 'net_value'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Get the sales project.
     */
    public function salesProject(): BelongsTo
    {
        return $this->belongsTo(SalesProject::class);
    }

    /**
     * Get the project demand.
     */
    public function projectDemand(): BelongsTo
    {
        return $this->belongsTo(ProjectDemand::class);
    }

    /**
     * Get the associate who made the delivery.
     */
    public function associate(): BelongsTo
    {
        return $this->belongsTo(Associate::class);
    }

    /**
     * Get the product delivered.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the user who received this delivery.
     */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /**
     * Get the user who approved this delivery.
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the payment for this delivery.
     */
    public function projectPayment(): BelongsTo
    {
        return $this->belongsTo(ProjectPayment::class, 'project_payment_id');
    }

    /**
     * Get the ledger entries for this delivery.
     */
    public function ledgerEntries(): MorphMany
    {
        return $this->morphMany(AssociateLedger::class, 'reference');
    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();
        
        // Auto-fill unit_price from demand when creating
        static::creating(function ($delivery) {
            if (!$delivery->unit_price && $delivery->project_demand_id) {
                $demand = $delivery->projectDemand;
                if ($demand) {
                    $delivery->unit_price = $demand->unit_price;
                    $delivery->product_id = $demand->product_id;
                }
            }
        });
        
        // Calculate admin fee and net value before saving
        static::saving(function ($delivery) {
            if ($delivery->unit_price && $delivery->quantity) {
                $grossValue = $delivery->quantity * $delivery->unit_price;
                
                // Get admin fee percentage from project
                if ($delivery->sales_project_id) {
                    $project = $delivery->salesProject;
                    $adminFeePercentage = $project->admin_fee_percentage ?? 10;
                    
                    $delivery->admin_fee_amount = $grossValue * ($adminFeePercentage / 100);
                    $delivery->net_value = $grossValue - $delivery->admin_fee_amount;
                }
            }
        });
    }

    /**
     * Calculate the gross value.
     */
    public function getGrossValueAttribute(): float
    {
        return $this->quantity * $this->unit_price;
    }

    /**
     * Check if delivery is pending.
     */
    public function isPending(): bool
    {
        return $this->status === DeliveryStatus::PENDING;
    }

    /**
     * Check if delivery is approved.
     */
    public function isApproved(): bool
    {
        return $this->status === DeliveryStatus::APPROVED;
    }

    /**
     * Check if delivery is paid.
     */
    public function isPaid(): bool
    {
        return $this->paid === true;
    }

    /**
     * Check if can be paid.
     */
    public function canBePaid(): bool
    {
        return $this->isApproved() && !$this->isPaid();
    }

    /**
     * Scope to get pending deliveries
     */
    public function scopePending($query)
    {
        return $query->where('status', DeliveryStatus::PENDING);
    }

    /**
     * Scope to get approved deliveries
     */
    public function scopeApproved($query)
    {
        return $query->where('status', DeliveryStatus::APPROVED);
    }

    /**
     * Scope to filter by associate
     */
    public function scopeForAssociate($query, int $associateId)
    {
        return $query->where('associate_id', $associateId);
    }

    /**
     * Scope to filter by date range
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('delivery_date', [$startDate, $endDate]);
    }
}
