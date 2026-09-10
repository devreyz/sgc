<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class ServiceCompositionLine extends Model
{
    use BelongsToTenant;
    protected $fillable = ['service_execution_id','direction','type','source_type','source_key','description','quantity','unit','unit_price','amount','financial_effect','rule_snapshot','manual','reason','created_by'];
    protected function casts(): array { return ['quantity'=>'decimal:4','unit_price'=>'decimal:4','amount'=>'decimal:2','rule_snapshot'=>'array','manual'=>'boolean']; }
    protected static function booted(): void { static::updating(fn()=>throw ValidationException::withMessages(['composition'=>'Composição congelada não pode ser editada.']));static::deleting(fn()=>throw ValidationException::withMessages(['composition'=>'Composição congelada não pode ser excluída.'])); }
    public function execution(): BelongsTo { return $this->belongsTo(ServiceExecution::class, 'service_execution_id'); }
}
