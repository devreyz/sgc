<?php

namespace App\Models;

use App\Enums\BillingStatus;
use App\Enums\DeliveryStatus;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ProductionDelivery extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'parent_delivery_id',
        'sales_project_id',
        'project_demand_id',
        'associate_id',
        'customer_id',
        'product_id',
        'delivery_date',
        'quantity',
        'unit_price',
        'cost_price_used',
        'admin_fee_percentage',
        'from_stock',
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
        'stock_movement_id',
        'billing_status',
        'distribution_billing_id',
        'associate_receipt_id',
        'billing_receipt_id',
        'price_table_id',
        'price_source',
    ];

    protected function casts(): array
    {
        return [
            'status' => DeliveryStatus::class,
            'delivery_date' => 'date',
            'quantity' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'cost_price_used' => 'decimal:4',
            'admin_fee_percentage' => 'decimal:2',
            'admin_fee_amount' => 'decimal:4',
            'net_value' => 'decimal:4',
            'from_stock' => 'boolean',
            'approved_at' => 'datetime',
            'paid' => 'boolean',
            'paid_date' => 'date',
            'billing_status'        => BillingStatus::class,
            'associate_receipt_id'  => 'integer',
            'billing_receipt_id'    => 'integer',
            'price_table_id'        => 'integer',
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
     * Get the parent (reception) delivery this distribution was created from.
     */
    public function parentDelivery(): BelongsTo
    {
        return $this->belongsTo(ProductionDelivery::class, 'parent_delivery_id');
    }

    /**
     * Get the child distributions created from this reception delivery.
     */
    public function distributions(): HasMany
    {
        return $this->hasMany(ProductionDelivery::class, 'parent_delivery_id');
    }

    /**
     * Get the billing batch this distribution belongs to.
     */
    public function distributionBilling(): BelongsTo
    {
        return $this->belongsTo(DistributionBilling::class, 'distribution_billing_id');
    }

    /** Comprovante (AssociateReceipt) que cobre esta distribuição. */
    public function associateReceipt(): BelongsTo
    {
        return $this->belongsTo(\App\Models\AssociateReceipt::class, 'associate_receipt_id');
    }

    public function billingReceipt(): BelongsTo
    {
        return $this->belongsTo(\App\Models\CustomerBillingReceipt::class, 'billing_receipt_id');
    }

    /**
     * Whether this is a reception record (no parent, no customer).
     */
    public function isReception(): bool
    {
        return is_null($this->parent_delivery_id) && is_null($this->customer_id);
    }

    /**
     * Whether this is a distribution record (has parent).
     */
    public function isDistribution(): bool
    {
        return !is_null($this->parent_delivery_id);
    }

    /**
     * Total quantity already distributed to customers from this reception.
     */
    public function getDistributedQuantityAttribute(): float
    {
        if ($this->isDistribution()) {
            return 0;
        }
        return (float) $this->distributions()->whereNotIn('status', ['rejected', 'cancelled'])->sum('quantity');
    }

    /**
     * Quantity still available for distribution.
     */
    public function getRemainingQuantityAttribute(): float
    {
        return max(0, (float) $this->quantity - $this->distributed_quantity);
    }

    /**
     * Get the sales project.
     */
    public function salesProject(): BelongsTo
    {
        return $this->belongsTo(SalesProject::class);
    }

    /**
     * Get the customer this delivery is destined to.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
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

    public function priceTable(): BelongsTo
    {
        return $this->belongsTo(\App\Models\PriceTable::class, 'price_table_id');
    }

    /**
     * Get expenses linked to this delivery (via expenseable polymorphic).
     */
    public function expenses(): MorphMany
    {
        return $this->morphMany(Expense::class, 'expenseable');
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
        
        // Calculate admin fee, cost price and net value before saving (BCMath)
        // Registros de recepção (sem cliente, sem parent) não calculam financeiro —
        // o cálculo ocorre nas distribuições filhas.
        static::saving(function ($delivery) {
            // Pula cálculo financeiro em registros de recepção pura
            if (is_null($delivery->customer_id) && is_null($delivery->parent_delivery_id)) {
                return;
            }

            if ($delivery->unit_price && $delivery->quantity) {
                $qty   = (string) $delivery->quantity;
                $price = (string) $delivery->unit_price;
                $grossValue = bcmul($qty, $price, 8);

                // Resolve admin fee percentage: persiste na entrega para histórico
                if ($delivery->sales_project_id && !$delivery->isDirty('admin_fee_percentage')) {
                    $project = $delivery->salesProject;
                    $adminFeePercentage = (string) ($project->admin_fee_percentage ?? 10);
                    $delivery->admin_fee_percentage = $adminFeePercentage;
                } else {
                    $adminFeePercentage = (string) ($delivery->admin_fee_percentage ?? 0);
                }

                $adminFee = bcmul($grossValue, bcdiv($adminFeePercentage, '100', 8), 8);
                $delivery->admin_fee_amount = $adminFee;
                $delivery->net_value = bcsub($grossValue, $adminFee, 8);

                // Calcula e persiste o cost_price_used (valor de repasse por unidade)
                if (!$delivery->cost_price_used) {
                    if (bccomp($adminFeePercentage, '0', 8) > 0) {
                        $taxPerUnit = bcmul($price, bcdiv($adminFeePercentage, '100', 8), 8);
                        $delivery->cost_price_used = bcsub($price, $taxPerUnit, 8);
                    } else {
                        $product = $delivery->product;
                        $delivery->cost_price_used = $product?->cost_price ?? $delivery->unit_price;
                    }
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
