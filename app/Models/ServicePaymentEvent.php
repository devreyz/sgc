<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class ServicePaymentEvent extends Model
{
    use BelongsToTenant;
    protected $fillable = ['operation_key','event_type','status','amount','payment_method','payment_date','bank_account_id','cash_movement_id','reversal_of_id','document_id','reason','metadata','registered_by'];
    protected function casts(): array { return ['amount'=>'decimal:2','payment_date'=>'date','metadata'=>'array']; }
    protected static function booted(): void { static::updating(function(self $event): void { $allowed=['status','cash_movement_id','updated_at'];if(array_diff(array_keys($event->getDirty()),$allowed))throw ValidationException::withMessages(['payment'=>'Evento financeiro confirmado é imutável.']);});static::deleting(fn()=>throw ValidationException::withMessages(['payment'=>'Pagamentos não são excluídos; use estorno.'])); }
    public function allocations(): HasMany { return $this->hasMany(ServicePaymentAllocation::class); }
    public function cashMovement(): BelongsTo { return $this->belongsTo(CashMovement::class); }
    public function reversalOf(): BelongsTo { return $this->belongsTo(self::class, 'reversal_of_id'); }
    public function document(): BelongsTo { return $this->belongsTo(Document::class); }
}
