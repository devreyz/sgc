<?php

namespace App\Models;

use App\Enums\ReceiptStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssociateReceipt extends Model
{
    protected $fillable = [
        'tenant_id',
        'sales_project_id',
        'associate_id',
        'receipt_year',
        'receipt_number',
        'issued_at',
        'from_date',
        'to_date',
        'notes',
        'acknowledged_at',
        'delivery_ids',
        // Novo: status do comprovante no fluxo financeiro
        'status',
        'obsolete_at',
        'obsolete_by',
        'obsolete_reason',
        // Novo: snapshot financeiro congelado
        'total_gross',
        'total_fees',
        'total_net',
        'fee_snapshot',
        // Novo: dados do pagamento efetivo
        'paid_at',
        'paid_by',
        'payment_method',
        'bank_account_id',
        'document_number',
        'payment_notes',
        'amount_paid',
    ];

    protected function casts(): array
    {
        return [
            'issued_at'       => 'date',
            'from_date'       => 'date',
            'to_date'         => 'date',
            'receipt_year'    => 'integer',
            'receipt_number'  => 'integer',
            'acknowledged_at' => 'datetime',
            'delivery_ids'    => 'array',
            'status'          => ReceiptStatus::class,
            'obsolete_at'     => 'datetime',
            'total_gross'     => 'decimal:4',
            'total_fees'      => 'decimal:4',
            'total_net'       => 'decimal:4',
            'fee_snapshot'    => 'array',
            'paid_at'         => 'datetime',
            'amount_paid'     => 'decimal:2',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(SalesProject::class, 'sales_project_id');
    }

    public function associate(): BelongsTo
    {
        return $this->belongsTo(Associate::class);
    }

    public function paidByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function obsoleteByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'obsolete_by');
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    /** Distribuições vinculadas a este comprovante (via associate_receipt_id). */
    public function distributions(): HasMany
    {
        return $this->hasMany(ProductionDelivery::class, 'associate_receipt_id');
    }

    /**
     * Pagamentos parciais / histórico de pagamentos ao associado.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(AssociateReceiptPayment::class, 'associate_receipt_id')
            ->orderBy('payment_date');
    }

    /**
     * Valor ainda não pago (total_net - amount_paid).
     */
    public function getRemainingAmountAttribute(): float
    {
        return max(0, (float) ($this->total_net ?? 0) - (float) ($this->amount_paid ?? 0));
    }

    /**
     * Gera o próximo número sequencial de recibo para um tenant/ano.
     */
    public static function nextNumber(int $tenantId, int $year): int
    {
        $max = static::where('tenant_id', $tenantId)
            ->where('receipt_year', $year)
            ->max('receipt_number');

        return ($max ?? 0) + 1;
    }

    /**
     * Número formatado: ex. "0042/2026"
     */
    public function getFormattedNumberAttribute(): string
    {
        return str_pad($this->receipt_number, 4, '0', STR_PAD_LEFT) . '/' . $this->receipt_year;
    }

    /**
     * Se o comprovante pode ser editado (itens, taxas, valores).
     */
    public function isEditable(): bool
    {
        return $this->status === null || $this->status->isEditable();
    }

    /**
     * Se o comprovante está congelado (pago).
     */
    public function isLocked(): bool
    {
        return $this->status?->isLocked() ?? false;
    }

    public function hasFinancialLocks(): bool
    {
        if ($this->status === ReceiptStatus::PAID || $this->status === ReceiptStatus::PARTIALLY_PAID) {
            return true;
        }

        if ((float) ($this->amount_paid ?? 0) > 0) {
            return true;
        }

        return $this->distributions()
            ->where(function ($query) {
                $query->where('paid', true)
                    ->orWhere('billing_status', '!=', \App\Enums\BillingStatus::UNBILLED->value)
                    ->orWhereNotNull('billing_receipt_id');
            })
            ->exists();
    }

    public function canBeOperationallyUpdated(): bool
    {
        return ! $this->hasFinancialLocks()
            && in_array($this->status, [
                null,
                ReceiptStatus::DRAFT,
                ReceiptStatus::OBSOLETE,
                ReceiptStatus::PENDING_PAYMENT,
            ], true);
    }

    public function markObsolete(string $reason, ?int $userId = null): void
    {
        if ($this->hasFinancialLocks()) {
            return;
        }

        $this->forceFill([
            'status' => ReceiptStatus::OBSOLETE,
            'obsolete_at' => now(),
            'obsolete_by' => $userId,
            'obsolete_reason' => $reason,
        ])->save();
    }
}
