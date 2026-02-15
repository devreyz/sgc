<?php

namespace App\Models;

use App\Enums\LoanPaymentStatus;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanPayment extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'loan_id',
        'installment_number',
        'amount',
        'interest',
        'fine',
        'total_paid',
        'due_date',
        'payment_date',
        'status',
        'payment_method',
        'ledger_entry_id',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => LoanPaymentStatus::class,
            'amount' => 'decimal:2',
            'interest' => 'decimal:2',
            'fine' => 'decimal:2',
            'total_paid' => 'decimal:2',
            'due_date' => 'date',
            'payment_date' => 'date',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function ledgerEntry(): BelongsTo
    {
        return $this->belongsTo(AssociateLedger::class, 'ledger_entry_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', LoanPaymentStatus::PENDING);
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', LoanPaymentStatus::OVERDUE)
            ->orWhere(function ($q) {
                $q->where('status', LoanPaymentStatus::PENDING)
                    ->where('due_date', '<', now());
            });
    }
}
