<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class FinancialCheckInstrument extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'financial_document_identity_id', 'operation_key', 'delivery_operation_key',
        'amount', 'check_number', 'bank_name', 'account_reference', 'issue_date',
        'expected_delivery_date', 'notes', 'status', 'issued_at', 'issued_by',
        'delivered_at', 'delivered_by', 'cancelled_at', 'cancelled_by', 'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'issue_date' => 'date',
            'expected_delivery_date' => 'date',
            'issued_at' => 'datetime',
            'delivered_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(fn () => throw ValidationException::withMessages([
            'check' => 'Cheques não são excluídos; use cancelamento ou preserve o histórico.',
        ]));
    }

    public function identity(): BelongsTo
    {
        return $this->belongsTo(FinancialDocumentIdentity::class, 'financial_document_identity_id');
    }
}
