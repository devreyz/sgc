<?php

namespace App\Models;

use App\Enums\CustomerReceiptStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerBillingReceipt extends Model
{
    protected $fillable = [
        'tenant_id',
        'sales_project_id',
        'customer_id',
        'organization_id',
        'receipt_year',
        'receipt_number',
        'issued_at',
        'from_date',
        'to_date',
        'notes',
        'delivery_ids',
        // Status do comprovante no fluxo financeiro
        'status',
        // Snapshot financeiro congelado
        'total_gross',
        'total_fees',
        'total_net',
        'fee_snapshot',
        // Dados do recebimento efetivo
        'paid_at',
        'paid_by',
        'payment_method',
        'bank_account_id',
        'document_number',
        'payment_notes',
        'amount_paid',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'issued_at'      => 'date',
            'from_date'      => 'date',
            'to_date'        => 'date',
            'receipt_year'   => 'integer',
            'receipt_number' => 'integer',
            'delivery_ids'   => 'array',
            'status'         => CustomerReceiptStatus::class,
            'total_gross'    => 'decimal:4',
            'total_fees'     => 'decimal:4',
            'total_net'      => 'decimal:4',
            'fee_snapshot'   => 'array',
            'paid_at'        => 'datetime',
            'amount_paid'    => 'decimal:2',
        ];
    }

    // ── Relacionamentos ──────────────────────────────────────────────────────

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(SalesProject::class, 'sales_project_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function paidByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    /**
     * Distribuições vinculadas a este comprovante (via billing_receipt_id).
     */
    public function billingDistributions(): HasMany
    {
        return $this->hasMany(ProductionDelivery::class, 'billing_receipt_id');
    }

    /**
     * Pagamentos parciais / histórico de recebimentos.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(CustomerReceiptPayment::class, 'customer_billing_receipt_id')
            ->orderBy('payment_date');
    }

    /**
     * Valor ainda não recebido (total_net - amount_paid).
     */
    public function getRemainingAmountAttribute(): float
    {
        return max(0, (float) ($this->total_net ?? 0) - (float) ($this->amount_paid ?? 0));
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Gera o próximo número sequencial de comprovante para um tenant/ano.
     */
    public static function nextNumber(int $tenantId, int $year): int
    {
        $max = static::where('tenant_id', $tenantId)
            ->where('receipt_year', $year)
            ->max('receipt_number');

        return ($max ?? 0) + 1;
    }

    /**
     * Número formatado: ex. "COM-0042/2026"
     */
    public function getFormattedNumberAttribute(): string
    {
        return 'COM-' . str_pad($this->receipt_number, 4, '0', STR_PAD_LEFT) . '/' . $this->receipt_year;
    }

    /**
     * Nome do destinatário (cliente ou organização).
     */
    public function getRecipientNameAttribute(): string
    {
        if ($this->organization_id && $this->organization) {
            return $this->organization->name;
        }
        if ($this->customer_id && $this->customer) {
            return $this->customer->name ?? '—';
        }
        return '—';
    }

    /**
     * Se o comprovante pode ser editado.
     */
    public function isEditable(): bool
    {
        return $this->status === null || $this->status->isEditable();
    }

    /**
     * Se o comprovante está congelado (emitido ou pago).
     */
    public function isLocked(): bool
    {
        return $this->status?->isLocked() ?? false;
    }
}
