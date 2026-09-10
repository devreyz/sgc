<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class ServiceExecution extends Model
{
    use BelongsToTenant;
    protected $fillable = ['service_order_id','service_version_id','service_provider_id','associate_id','status','revision','lock_version','quantity','unit','values','derived_values','catalog_snapshot','snapshot_hash','started_at','started_by','submitted_at','submitted_by','validated_at','validated_by','rejected_at','reviewed_by','review_reason','start_operation_key','submit_operation_key','review_operation_key'];
    protected function casts(): array { return ['quantity'=>'decimal:4','values'=>'array','derived_values'=>'array','catalog_snapshot'=>'array','started_at'=>'datetime','submitted_at'=>'datetime','validated_at'=>'datetime','rejected_at'=>'datetime']; }
    protected static function booted(): void { static::updating(function(self $execution): void { if($execution->getOriginal('status')==='validated')throw ValidationException::withMessages(['execution'=>'Execução validada é imutável.']); }); static::deleting(fn()=>throw ValidationException::withMessages(['execution'=>'Execuções não são excluídas; use cancelamento.'])); }
    public function order(): BelongsTo { return $this->belongsTo(ServiceOrder::class, 'service_order_id'); }
    public function version(): BelongsTo { return $this->belongsTo(ServiceVersion::class, 'service_version_id'); }
    public function provider(): BelongsTo { return $this->belongsTo(ServiceProvider::class, 'service_provider_id'); }
    public function associate(): BelongsTo { return $this->belongsTo(Associate::class); }
    public function evidences(): HasMany { return $this->hasMany(ServiceExecutionEvidence::class); }
    public function resources(): HasMany { return $this->hasMany(ServiceExecutionResource::class); }
    public function compositionLines(): HasMany { return $this->hasMany(ServiceCompositionLine::class); }
    public function obligations(): HasMany { return $this->hasMany(ServiceObligation::class); }
    public function isFrozen(): bool { return $this->status === 'validated'; }
}
