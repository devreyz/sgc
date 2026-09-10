<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class ServiceObligation extends Model
{
    use BelongsToTenant;
    protected $fillable = ['number','service_execution_id','direction','associate_id','service_provider_id','principal_amount','adjustment_amount','status','due_date','party_snapshot','composition_snapshot','snapshot_hash','operation_key','frozen_at','created_by'];
    protected function casts(): array { return ['principal_amount'=>'decimal:2','adjustment_amount'=>'decimal:2','due_date'=>'date','party_snapshot'=>'array','composition_snapshot'=>'array','frozen_at'=>'datetime']; }
    protected static function booted(): void { static::updating(function(self $obligation): void { $allowed=['status','adjustment_amount','updated_at'];if(array_diff(array_keys($obligation->getDirty()),$allowed))throw ValidationException::withMessages(['obligation'=>'Principal e composição da obrigação são imutáveis.']);});static::deleting(fn()=>throw ValidationException::withMessages(['obligation'=>'Obrigações não são excluídas; use cancelamento ou ajuste.'])); }
    public function execution(): BelongsTo { return $this->belongsTo(ServiceExecution::class, 'service_execution_id'); }
    public function associate(): BelongsTo { return $this->belongsTo(Associate::class); }
    public function provider(): BelongsTo { return $this->belongsTo(ServiceProvider::class, 'service_provider_id'); }
    public function allocations(): HasMany { return $this->hasMany(ServicePaymentAllocation::class); }
    public function adjustments(): HasMany { return $this->hasMany(ServiceObligationAdjustment::class); }
    public function getTotalAmountAttribute(): float { return round((float)$this->principal_amount + (float)$this->adjustment_amount, 2); }
    public function getPaidAmountAttribute(): float { return round((float)$this->allocations()->whereHas('paymentEvent', fn($q) => $q->whereIn('status',['confirmed','reversed']))->sum('amount'), 2); }
    public function getBalanceAttribute(): float { return max(0, round($this->total_amount - $this->paid_amount, 2)); }
}
