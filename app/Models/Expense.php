<?php

namespace App\Models;

use App\Enums\ExpenseStatus;
use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Expense extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'description',
        'document_number',
        'amount',
        'discount',
        'interest',
        'fine',
        'paid_amount',
        'date',
        'due_date',
        'paid_date',
        'chart_account_id',
        'bank_account_id',
        'supplier_id',
        'expenseable_type',
        'expenseable_id',
        'status',
        'payment_method',
        'is_recurring',
        'installment_number',
        'total_installments',
        'parent_expense_id',
        'document_path',
        'notes',
        'created_by',
        'paid_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => ExpenseStatus::class,
            'payment_method' => PaymentMethod::class,
            'amount' => 'decimal:2',
            'discount' => 'decimal:2',
            'interest' => 'decimal:2',
            'fine' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'date' => 'date',
            'due_date' => 'date',
            'paid_date' => 'date',
            'is_recurring' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['description', 'amount', 'status', 'due_date', 'paid_date', 'paid_amount'])
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
     * Get the supplier.
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Get the expenseable model (polymorphic: Asset, SalesProject, User).
     */
    public function expenseable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the parent expense (for installments).
     */
    public function parentExpense(): BelongsTo
    {
        return $this->belongsTo(Expense::class, 'parent_expense_id');
    }

    /**
     * Get the installments.
     */
    public function installments(): HasMany
    {
        return $this->hasMany(Expense::class, 'parent_expense_id');
    }

    /**
     * Get the user who created this expense.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who paid this expense.
     */
    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    /**
     * Calculate the total amount to pay.
     */
    public function getTotalAmountAttribute(): float
    {
        return $this->amount - $this->discount + $this->interest + $this->fine;
    }

    /**
     * Check if expense is overdue.
     */
    public function isOverdue(): bool
    {
        return $this->status !== ExpenseStatus::PAID 
            && $this->due_date 
            && $this->due_date->isPast();
    }

    /**
     * Scope to get pending expenses
     */
    public function scopePending($query)
    {
        return $query->where('status', ExpenseStatus::PENDING);
    }

    /**
     * Scope to get paid expenses
     */
    public function scopePaid($query)
    {
        return $query->where('status', ExpenseStatus::PAID);
    }

    /**
     * Scope to get overdue expenses
     */
    public function scopeOverdue($query)
    {
        return $query->where('status', ExpenseStatus::PENDING)
            ->where('due_date', '<', now());
    }

    /**
     * Scope to filter by date range
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('due_date', [$startDate, $endDate]);
    }
}
