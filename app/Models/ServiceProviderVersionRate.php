<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class ServiceProviderVersionRate extends Model
{
    use BelongsToTenant;

    protected $fillable = ['service_version_id', 'service_provider_id', 'calculation_method', 'rate', 'percentage', 'fixed_amount', 'active', 'notes'];

    protected function casts(): array
    {
        return ['rate' => 'decimal:4', 'percentage' => 'decimal:4', 'fixed_amount' => 'decimal:2', 'active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $rate): void {
            if ($rate->version?->isPublished()) {
                throw ValidationException::withMessages(['rate' => 'Tarifas de versão publicada são imutáveis. Duplique a versão para alterá-las.']);
            }
        });
        static::deleting(function (self $rate): void {
            if ($rate->version?->isPublished()) {
                throw ValidationException::withMessages(['rate' => 'Tarifas de versão publicada não podem ser excluídas.']);
            }
        });
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(ServiceVersion::class, 'service_version_id');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(ServiceProvider::class, 'service_provider_id');
    }
}
