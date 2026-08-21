<?php

namespace App\Models;

use App\Enums\CustomerReceiptStatus;
use App\Services\ProjectReceiptNumberingService;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class CustomerBillingReceipt extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'sales_project_id',
        'customer_id',
        'organization_id',
        'receipt_year',
        'receipt_number',
        'receipt_label',
        'tenant_receipt_year',
        'tenant_receipt_number',
        'project_receipt_year',
        'project_receipt_number',
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
            'issued_at' => 'date',
            'from_date' => 'date',
            'to_date' => 'date',
            'receipt_year' => 'integer',
            'receipt_number' => 'integer',
            'tenant_receipt_year' => 'integer',
            'tenant_receipt_number' => 'integer',
            'project_receipt_year' => 'integer',
            'project_receipt_number' => 'integer',
            'delivery_ids' => 'array',
            'status' => CustomerReceiptStatus::class,
            'total_gross' => 'decimal:4',
            'total_fees' => 'decimal:4',
            'total_net' => 'decimal:4',
            'fee_snapshot' => 'array',
            'paid_at' => 'datetime',
            'amount_paid' => 'decimal:2',
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

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function authorizationRounds(): HasMany
    {
        return $this->hasMany(BillingAuthorization::class, 'customer_billing_receipt_id')
            ->orderByDesc('sequence');
    }

    public function currentAuthorization(): HasMany
    {
        return $this->hasMany(BillingAuthorization::class, 'customer_billing_receipt_id')
            ->where('active_marker', true)
            ->orderByDesc('sequence');
    }

    public function latestAuthorizationRound(): HasOne
    {
        return $this->hasOne(BillingAuthorization::class, 'customer_billing_receipt_id')->ofMany('sequence', 'max');
    }

    public function activeAuthorization(): HasOne
    {
        return $this->hasOne(BillingAuthorization::class, 'customer_billing_receipt_id')
            ->where('active_marker', true)->ofMany('sequence', 'max');
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
    public static function nextNumber(int $tenantId, int $year, SalesProject|int|null $project = null): int
    {
        if (is_int($project)) {
            $project = SalesProject::query()
                ->where('tenant_id', $tenantId)
                ->find($project);
        }

        return app(ProjectReceiptNumberingService::class)
            ->nextNumber(static::class, $tenantId, $year, $project);
    }

    /** @return array{receipt_year: int, receipt_number: int, receipt_label: string} */
    public static function numberingFor(SalesProject $project, mixed $issuedAt = null): array
    {
        return app(ProjectReceiptNumberingService::class)
            ->numberingFor(static::class, $project, 'COM-', $issuedAt);
    }

    /**
     * Número formatado: ex. "COM-0042/2026"
     */
    public function getFormattedNumberAttribute(): string
    {
        $project = $this->project;
        if ($project && $this->project_receipt_number && $this->tenant_receipt_number) {
            if (app(ProjectReceiptNumberingService::class)->usesProjectSequence($project)) {
                return app(ProjectReceiptNumberingService::class)->format(
                    $project,
                    (int) $this->project_receipt_number,
                    (int) $this->project_receipt_year,
                    'COM-',
                );
            }

            return app(ProjectReceiptNumberingService::class)->format(
                $project,
                (int) $this->tenant_receipt_number,
                (int) $this->tenant_receipt_year,
                'COM-',
            );
        }

        return filled($this->receipt_label)
            ? (string) $this->receipt_label
            : 'COM-'.str_pad($this->receipt_number, 4, '0', STR_PAD_LEFT).'/'.$this->receipt_year;
    }

    public function getTenantFormattedNumberAttribute(): string
    {
        return app(ProjectReceiptNumberingService::class)->formatTenant(
            (int) ($this->tenant_receipt_number ?: $this->receipt_number),
            (int) ($this->tenant_receipt_year ?: $this->receipt_year),
            'COM-',
        );
    }

    public function getProjectFormattedNumberAttribute(): string
    {
        if (! $this->project) {
            return (string) $this->receipt_label;
        }

        return app(ProjectReceiptNumberingService::class)->format(
            $this->project,
            (int) ($this->project_receipt_number ?: $this->receipt_number),
            (int) ($this->project_receipt_year ?: $this->receipt_year),
            'COM-',
        );
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
