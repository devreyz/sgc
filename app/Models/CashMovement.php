<?php

namespace App\Models;

use App\Enums\CashMovementType;
use App\Enums\PaymentMethod;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class CashMovement extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'type',
        'amount',
        'balance_after',
        'description',
        'movement_date',
        'bank_account_id',
        'transfer_to_account_id',
        'reference_type',
        'reference_id',
        'chart_account_id',
        'payment_method',
        'document_number',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => CashMovementType::class,
            'payment_method' => PaymentMethod::class,
            'amount' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'movement_date' => 'date',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['type', 'amount', 'description', 'movement_date'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Get the bank account.
     */
    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    /**
     * Get the transfer destination account.
     */
    public function transferToAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'transfer_to_account_id');
    }

    /**
     * Get the chart account.
     */
    public function chartAccount(): BelongsTo
    {
        return $this->belongsTo(ChartAccount::class);
    }

    /**
     * Get the reference model (polymorphic).
     */
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the user who created this movement.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope to get income movements
     */
    public function scopeIncome($query)
    {
        return $query->where('type', CashMovementType::INCOME);
    }

    /**
     * Scope to get expense movements
     */
    public function scopeExpense($query)
    {
        return $query->where('type', CashMovementType::EXPENSE);
    }

    /**
     * Scope to get transfer movements
     */
    public function scopeTransfer($query)
    {
        return $query->where('type', CashMovementType::TRANSFER);
    }

    /**
     * Scope to filter by date range
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('movement_date', [$startDate, $endDate]);
    }
}
