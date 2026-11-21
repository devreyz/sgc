<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Services\Services\ServiceMoney;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Facades\Schema;

class ServicePaymentPlanInstallment extends Model
{
    use BelongsToTenant;

    protected $fillable = ['service_payment_plan_id', 'number', 'kind', 'due_date', 'amount', 'status', 'service_payment_event_id', 'paid_at'];

    protected function casts(): array
    {
        return ['due_date' => 'date', 'amount' => 'decimal:2', 'paid_at' => 'datetime'];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(ServicePaymentPlan::class, 'service_payment_plan_id');
    }

    public function paymentEvent(): BelongsTo
    {
        return $this->belongsTo(ServicePaymentEvent::class, 'service_payment_event_id');
    }

    public function receivedCents(?Collection $events = null): int
    {
        $events ??= $this->plan?->receiptEvents()->get() ?? collect();

        $received = $events->where('status', 'confirmed')->sum(fn (ServicePaymentEvent $event) => $this->eventCents($event));
        if ($this->service_payment_event_id && ! $events->contains('id', $this->service_payment_event_id)) {
            $legacy = $this->paymentEvent;
            $received += $legacy?->status === 'confirmed' ? ServiceMoney::cents($legacy->amount) : 0;
        }

        return $received;
    }

    public function eventCents(ServicePaymentEvent $event): int
    {
        if (isset($event->metadata['installments'][$this->id])) {
            return (int) $event->metadata['installments'][$this->id]['cents'];
        }

        $matches = (int) ($event->metadata['payment_plan_installment_id'] ?? 0) === (int) $this->id
            || (int) $this->service_payment_event_id === (int) $event->id;

        return $matches ? ServiceMoney::cents($event->amount) : 0;
    }

    public function balanceCents(?Collection $events = null): int
    {
        return max(0, ServiceMoney::cents($this->amount) - $this->receivedCents($events));
    }

    public function financialLabel(Collection $events): string
    {
        if ($this->status === 'cancelled') {
            return 'Cancelada';
        }
        $received = $this->receivedCents($events);
        if ($received >= ServiceMoney::cents($this->amount)) {
            return 'Quitada';
        }
        if ($this->due_date->lt(today())) {
            return $received > 0 ? 'Vencida · parcial' : 'Vencida';
        }

        return $received > 0 ? 'Parcialmente paga' : 'Pendente';
    }

    public function verificationIdentity(): MorphOne
    {
        $relation = $this->morphOne(FinancialDocumentIdentity::class, 'documentable');

        return Schema::hasColumn('financial_document_identities', 'revision')
            ? $relation->whereNull('invalidated_at')->latestOfMany('revision')
            : $relation;
    }
}
