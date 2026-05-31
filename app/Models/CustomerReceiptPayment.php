<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerReceiptPayment extends Model
{
    protected $fillable = [
        'tenant_id',
        'customer_billing_receipt_id',
        'amount',
        'payment_date',
        'payment_method',
        'bank_account_id',
        'document_number',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount'       => 'decimal:2',
    ];

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(CustomerBillingReceipt::class, 'customer_billing_receipt_id');
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }
}
