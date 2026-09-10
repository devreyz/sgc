<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServicePaymentPlanInstallment extends Model
{
    use BelongsToTenant;
    protected $fillable = ['service_payment_plan_id','number','due_date','amount','status'];
    protected function casts(): array { return ['due_date'=>'date','amount'=>'decimal:2']; }
    public function plan(): BelongsTo { return $this->belongsTo(ServicePaymentPlan::class, 'service_payment_plan_id'); }
}
