<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServicePayoutRequest extends Model
{
    use BelongsToTenant;
    protected $fillable = ['service_provider_id','operation_key','amount','status','bank_snapshot','notes','requested_by','reviewed_by','reviewed_at'];
    protected function casts(): array { return ['amount'=>'decimal:2','bank_snapshot'=>'array','reviewed_at'=>'datetime']; }
    public function provider(): BelongsTo { return $this->belongsTo(ServiceProvider::class, 'service_provider_id'); }
    public function items(): HasMany { return $this->hasMany(ServicePayoutRequestItem::class); }
}
