<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Revenue extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'description',
        'document_number',
        'amount',
        'discount',
        'received_amount',
        'date',
        'due_date',
        'received_date',
        'chart_account_id',
        'bank_account_id',
        'customer_id',
        'revenueable_type',
        'revenueable_id',
        'status',
        'payment_method',
        'document_path',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'payment_method' => PaymentMethod::class,
            'amount' => 'decimal:2',
            'discount' => 'decimal:2',
            'received_amount' => 'decimal:2',
            'date' => 'date',
            'due_date' => 'date',
            'received_date' => 'date',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['description', 'amount', 'status', 'received_date', 'received_amount'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Get the chart account.
     */
    public function chartAccount(): BelongsTo
    {
        return $this->belongsTo(ChartAccount::class);
    }

    /**
     * Get the bank account.
     */
    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    /**
     * Get the customer.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the revenueable model (polymorphic).
     */
    public function revenueable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the user who created this revenue.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Calculate the net amount.
     */
    public function getNetAmountAttribute(): float
    {
        return $this->amount - $this->discount;
    }

    /**
     * Scope to get pending revenues
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope to get received revenues
     */
    public function scopeReceived($query)
    {
        return $query->where('status', 'received');
    }

    /**
     * Scope to filter by date range
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }
}
