<?php

namespace App\Models;

use App\Enums\ProjectPaymentStatus;
use App\Enums\PaymentMethod;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ProjectPayment extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'sales_project_id',
        'type',
        'status',
        'amount',
        'balance_remaining',
        'description',
        'payment_date',
        'expected_date',
        'bank_account_id',
        'payment_method',
        'document_number',
        'associate_id',
        'production_delivery_id',
        'notes',
        'receipt_number',
        'finalized_at',
        'finalized_by',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ProjectPaymentStatus::class,
            'payment_method' => PaymentMethod::class,
            'amount' => 'decimal:2',
            'balance_remaining' => 'decimal:2',
            'payment_date' => 'date',
            'expected_date' => 'date',
            'approved_at' => 'datetime',
            'finalized_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['type', 'status', 'amount', 'payment_date'])
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
     * Get the bank account.
     */
    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    /**
     * Get the associate (for associate payments).
     */
    public function associate(): BelongsTo
    {
        return $this->belongsTo(Associate::class);
    }

    /**
     * Get the production delivery (for associate payments).
     */
    public function productionDelivery(): BelongsTo
    {
        return $this->belongsTo(ProductionDelivery::class);
    }

    /**
     * Get the user who created this payment.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who approved this payment.
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Scope to get client payments
     */
    public function scopeClientPayments($query)
    {
        return $query->where('type', 'client_payment');
    }

    /**
     * Scope to get associate payments
     */
    public function scopeAssociatePayments($query)
    {
        return $query->where('type', 'associate_payment');
    }

    /**
     * Get distributions linked to this payment.
     */
    public function distributions()
    {
        return $this->hasMany(ProductionDelivery::class, 'project_payment_id');
    }

    /**
     * Check if this payment is finalized (immutable).
     */
    public function isFinalized(): bool
    {
        return !is_null($this->finalized_at);
    }

    /**
     * Generate the next receipt number for associate payments in a year.
     */
    public static function nextReceiptNumber(int $tenantId, int $year): string
    {
        $max = static::where('tenant_id', $tenantId)
            ->where('type', 'associate_payment')
            ->whereNotNull('receipt_number')
            ->where('receipt_number', 'like', "PAG-{$year}-%")
            ->count();
        return sprintf('PAG-%d-%04d', $year, $max + 1);
    }

    /**
     * Scope to get pending payments
     */
    public function scopePending($query)
    {
        return $query->where('status', ProjectPaymentStatus::PENDING);
    }

    /**
     * Scope to get paid payments
     */
    public function scopePaid($query)
    {
        return $query->where('status', ProjectPaymentStatus::PAID);
    }
}
