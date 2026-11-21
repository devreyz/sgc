<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServicePaymentPlan extends Model
{
    use BelongsToTenant;

    protected $fillable = ['status', 'description', 'created_by'];

    public function obligations(): BelongsToMany
    {
        return $this->belongsToMany(ServiceObligation::class, 'service_payment_plan_obligations')->withPivot('included_amount');
    }

    public function installments(): HasMany
    {
        return $this->hasMany(ServicePaymentPlanInstallment::class)->orderBy('number');
    }

    public function receiptEvents(): Builder
    {
        return ServicePaymentEvent::query()->where('tenant_id', $this->tenant_id)
            ->where('event_type', 'payment')->where('metadata->payment_plan_id', (int) $this->id);
    }
}
