<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\ServiceOrderPaymentStatus;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ServiceOrderPayment extends Model
{
    use BelongsToTenant, HasFactory, LogsActivity;

    protected $fillable = [
        'service_order_id',
        'type',
        'status',
        'payment_date',
        'amount',
        'discount',
        'fees',
        'final_amount',
        'payment_method',
        'bank_account_id',
        'notes',
        'receipt_path',
        'registered_by',
    ];

    protected function casts(): array
    {
        return [
            'payment_date' => 'date',
            'amount' => 'decimal:2',
            'discount' => 'decimal:2',
            'fees' => 'decimal:2',
            'final_amount' => 'decimal:2',
            'payment_method' => PaymentMethod::class,
            'status' => ServiceOrderPaymentStatus::class,
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['service_order_id', 'type', 'payment_date', 'amount', 'payment_method'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    // Scopes
    public function scopeClientPayments($query)
    {
        return $query->where('type', 'client');
    }

    public function scopeProviderPayments($query)
    {
        return $query->where('type', 'provider');
    }
}
