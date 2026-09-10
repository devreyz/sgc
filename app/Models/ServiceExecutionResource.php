<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class ServiceExecutionResource extends Model
{
    use BelongsToTenant;
    protected $fillable = ['service_execution_id','resource_key','description','provided_by','effect','quantity','unit','unit_price','amount','product_id','expense_id','operation_key','metadata','created_by'];
    protected function casts(): array { return ['quantity'=>'decimal:4','unit_price'=>'decimal:4','amount'=>'decimal:2','metadata'=>'array']; }
    protected static function booted(): void { static::saving(function(self $resource): void { if($resource->exists&&$resource->execution?->isFrozen())throw ValidationException::withMessages(['resource'=>'Recurso de execução validada é imutável.']);});static::deleting(function(self $resource): void { if($resource->execution?->isFrozen())throw ValidationException::withMessages(['resource'=>'Recurso de execução validada não pode ser excluído.']);}); }
    public function execution(): BelongsTo { return $this->belongsTo(ServiceExecution::class, 'service_execution_id'); }
    public function expense(): BelongsTo { return $this->belongsTo(Expense::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
}
