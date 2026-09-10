<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServicePayoutRequestItem extends Model
{
    public $timestamps = false;
    protected $fillable = ['service_payout_request_id','service_obligation_id','amount'];
    protected function casts(): array { return ['amount'=>'decimal:2']; }
    public function request(): BelongsTo { return $this->belongsTo(ServicePayoutRequest::class, 'service_payout_request_id'); }
    public function obligation(): BelongsTo { return $this->belongsTo(ServiceObligation::class, 'service_obligation_id'); }
}
