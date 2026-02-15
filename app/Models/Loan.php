<?php

namespace App\Models;

use App\Enums\LoanStatus;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Loan extends Model
{
    use BelongsToTenant, HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'associate_id',
        'amount',
        'interest_rate',
        'total_with_interest',
        'paid_amount',
        'balance',
        'installments',
        'installment_value',
        'paid_installments',
        'loan_date',
        'first_payment_date',
        'last_payment_date',
        'status',
        'purpose',
        'notes',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => LoanStatus::class,
            'amount' => 'decimal:2',
            'interest_rate' => 'decimal:2',
            'total_with_interest' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'balance' => 'decimal:2',
            'installment_value' => 'decimal:2',
            'loan_date' => 'date',
            'first_payment_date' => 'date',
            'last_payment_date' => 'date',
            'approved_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'amount', 'paid_amount', 'balance'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            // Calcular total com juros
            if (isset($model->amount) && isset($model->interest_rate)) {
                $interest = $model->amount * ($model->interest_rate / 100);
                $model->total_with_interest = $model->amount + $interest;
            }

            // Calcular valor da parcela
            if (isset($model->total_with_interest) && isset($model->installments)) {
                $model->installment_value = $model->total_with_interest / $model->installments;
            }

            // Saldo inicial = total com juros
            if (!isset($model->balance)) {
                $model->balance = $model->total_with_interest;
            }
        });
    }

    public function associate(): BelongsTo
    {
        return $this->belongsTo(Associate::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(LoanPayment::class);
    }

    public function ledgerEntries(): MorphMany
    {
        return $this->morphMany(AssociateLedger::class, 'reference');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function updateBalance(): void
    {
        $this->paid_amount = $this->payments()->where('status', 'paid')->sum('total_paid');
        $this->balance = $this->total_with_interest - $this->paid_amount;
        $this->paid_installments = $this->payments()->where('status', 'paid')->count();

        // Atualizar status
        if ($this->balance <= 0) {
            $this->status = LoanStatus::PAID;
            $this->last_payment_date = now();
        } elseif ($this->payments()->where('status', 'overdue')->exists()) {
            $this->status = LoanStatus::OVERDUE;
        }

        $this->save();
    }

    public function scopeActive($query)
    {
        return $query->where('status', LoanStatus::ACTIVE);
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', LoanStatus::OVERDUE);
    }
}
