<?php

namespace App\Models;

use App\Enums\FinancialReceiptStatus;
use App\Enums\PaymentMethod;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class FinancialReceipt extends Model
{
    use BelongsToTenant, LogsActivity;

    protected $fillable = [
        'payer_type', 'payer_name', 'payer_document', 'payer_contact', 'received_on',
        'bank_account_id', 'chart_account_id', 'payment_method', 'payment_reference',
        'manual_amount', 'purpose', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => FinancialReceiptStatus::class,
            'payment_method' => PaymentMethod::class,
            'received_on' => 'date',
            'issued_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'total_amount' => 'decimal:2',
            'manual_amount' => 'decimal:2',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(FinancialReceiptItem::class)->orderBy('position');
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function chartAccount(): BelongsTo
    {
        return $this->belongsTo(ChartAccount::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function cashMovement(): BelongsTo
    {
        return $this->belongsTo(CashMovement::class);
    }

    public function reversalMovement(): BelongsTo
    {
        return $this->belongsTo(CashMovement::class, 'reversal_movement_id');
    }

    public function isDraft(): bool
    {
        return $this->status === FinancialReceiptStatus::DRAFT;
    }

    public function isIssued(): bool
    {
        return $this->status === FinancialReceiptStatus::ISSUED;
    }

    public function getFormattedNumberAttribute(): string
    {
        return $this->receipt_number
            ? sprintf('REC-%d/%06d', $this->receipt_year, $this->receipt_number)
            : 'RASCUNHO #'.$this->id;
    }

    public function recalculateTotal(): void
    {
        $itemsTotal = (float) $this->items()->sum('total_amount');
        $total = $itemsTotal > 0 ? $itemsTotal : (float) $this->manual_amount;

        $this->forceFill(['total_amount' => round($total, 2)])->saveQuietly();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly([
            'status', 'payer_name', 'received_on', 'total_amount', 'bank_account_id',
            'payment_method', 'issued_at', 'cancelled_at', 'cancellation_reason',
        ])->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
