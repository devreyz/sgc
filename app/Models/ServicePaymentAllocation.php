<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServicePaymentAllocation extends Model
{
    use BelongsToTenant;
    protected $fillable = ['service_payment_event_id','service_obligation_id','amount'];
    protected function casts(): array { return ['amount'=>'decimal:2']; }
    public function paymentEvent(): BelongsTo { return $this->belongsTo(ServicePaymentEvent::class, 'service_payment_event_id'); }
    public function obligation(): BelongsTo { return $this->belongsTo(ServiceObligation::class, 'service_obligation_id'); }
}
