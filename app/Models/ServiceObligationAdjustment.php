<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceObligationAdjustment extends Model
{
    use BelongsToTenant;
    protected $fillable=['service_obligation_id','operation_key','type','amount','reason','reversal_of_id','created_by'];
    protected function casts(): array{return ['amount'=>'decimal:2'];}
    public function obligation(): BelongsTo{return $this->belongsTo(ServiceObligation::class,'service_obligation_id');}
    public function reversalOf(): BelongsTo{return $this->belongsTo(self::class,'reversal_of_id');}
}
