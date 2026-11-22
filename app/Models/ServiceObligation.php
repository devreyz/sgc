<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Services\Services\ServiceMoney;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ServiceObligation extends Model
{
    use BelongsToTenant;

    protected $fillable = ['number', 'service_execution_id', 'direction', 'associate_id', 'service_provider_id', 'principal_amount', 'adjustment_amount', 'status', 'due_date', 'party_snapshot', 'composition_snapshot', 'snapshot_hash', 'operation_key', 'frozen_at', 'created_by'];

    protected function casts(): array
    {
        return ['principal_amount' => 'decimal:2', 'adjustment_amount' => 'decimal:2', 'due_date' => 'date', 'party_snapshot' => 'array', 'composition_snapshot' => 'array', 'frozen_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $obligation): void {
            $allowed = ['status', 'adjustment_amount', 'updated_at'];
            if (array_diff(array_keys($obligation->getDirty()), $allowed)) {
                throw ValidationException::withMessages(['obligation' => 'Principal e composição da obrigação são imutáveis.']);
            }
        });
        static::deleting(fn () => throw ValidationException::withMessages(['obligation' => 'Obrigações não são excluídas; use cancelamento ou ajuste.']));
    }

    public function execution(): BelongsTo
    {
        return $this->belongsTo(ServiceExecution::class, 'service_execution_id');
    }

    public function associate(): BelongsTo
    {
        return $this->belongsTo(Associate::class)->withTrashed();
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(ServiceProvider::class, 'service_provider_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(ServicePaymentAllocation::class);
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(ServiceObligationAdjustment::class);
    }

    public function latestRateAdjustment(): HasOne
    {
        return $this->adjustments()->one()->ofMany(['id' => 'max'], fn ($query) =>
            $query->whereIn('metadata->kind', ['provider_rate', 'customer_rate']));
    }

    public function rateBaseLine(): ?array
    {
        $source = $this->direction === 'payable' ? 'provider_compensation' : 'customer_base';

        return collect(data_get($this->composition_snapshot, 'lines', []))
            ->first(fn ($line) => ($line['type'] ?? null) === 'base' && ($line['source_key'] ?? null) === $source);
    }

    public function verificationIdentity(): MorphOne
    {
        $relation = $this->morphOne(FinancialDocumentIdentity::class, 'documentable');

        return Schema::hasColumn('financial_document_identities', 'revision')
            ? $relation->whereNull('invalidated_at')->latestOfMany('revision')
            : $relation;
    }

    public function getTotalAmountAttribute(): float
    {
        return $this->totalCents() / 100;
    }

    public function getPaidAmountAttribute(): float
    {
        return $this->paidCents() / 100;
    }

    public function getBalanceAttribute(): float
    {
        return $this->balanceCents() / 100;
    }

    public function totalCents(): int
    {
        return ServiceMoney::cents($this->principal_amount) + ServiceMoney::cents($this->adjustment_amount ?? 0);
    }

    public function paidCents(): int
    {
        return ServiceMoney::cents($this->getAttribute('settled_amount') ?? $this->allocations()
            ->whereHas('paymentEvent', fn ($query) => $query->whereIn('status', ['confirmed', 'reversed']))->sum('amount'));
    }

    public function balanceCents(): int
    {
        return $this->status === 'cancelled' ? 0 : max(0, $this->totalCents() - $this->paidCents());
    }

    public function activePaymentPlan(?int $exceptPlan = null): bool
    {
        return \Illuminate\Support\Facades\DB::table('service_payment_plan_obligations as link')
            ->join('service_payment_plans as plan', 'plan.id', '=', 'link.service_payment_plan_id')
            ->where('link.service_obligation_id', $this->id)
            ->when($exceptPlan, fn ($query) => $query->where('plan.id', '!=', $exceptPlan))
            ->where('plan.tenant_id', $this->tenant_id)->where('plan.status', 'active')->exists();
    }
}
