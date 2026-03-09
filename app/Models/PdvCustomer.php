<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PdvCustomer extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $hidden = [
        'tenant_id',
        'deleted_at',
    ];

    protected $fillable = [
        'name',
        'cpf_cnpj',
        'phone',
        'email',
        'address',
        'credit_limit',
        'credit_balance',
        'notes',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'credit_limit' => 'decimal:2',
            'credit_balance' => 'decimal:2',
            'status' => 'boolean',
        ];
    }

    public function sales(): HasMany
    {
        return $this->hasMany(PdvSale::class);
    }

    public function getFiadoBalanceAttribute(): float
    {
        return (float) $this->sales()
            ->where('status', 'completed')
            ->where('is_fiado', true)
            ->whereRaw('total > amount_paid')
            ->get()
            ->sum(fn ($s) => $s->fiado_remaining);
    }

    public function scopeActive($query)
    {
        return $query->where('status', true);
    }
}
