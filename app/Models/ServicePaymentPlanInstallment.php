<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;

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

    public function verificationIdentity(): MorphOne
    {
        return $this->morphOne(FinancialDocumentIdentity::class, 'documentable');
    }
}
