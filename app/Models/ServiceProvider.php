<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceProvider extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'cpf',
        'rg',
        'phone',
        'email',
        'type',
        'address',
        'city',
        'state',
        'zip_code',
        'bank_name',
        'bank_agency',
        'bank_account',
        'pix_key',
        'hourly_rate',
        'daily_rate',
        'current_balance',
        'notes',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'hourly_rate' => 'decimal:2',
            'daily_rate' => 'decimal:2',
            'status' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function works(): HasMany
    {
        return $this->hasMany(ServiceProviderWork::class);
    }

    public function ledgers(): HasMany
    {
        return $this->hasMany(ServiceProviderLedger::class);
    }

    public function pendingWorks(): HasMany
    {
        return $this->works()->where('payment_status', 'pendente');
    }

    public function getCurrentBalanceAttribute(): float
    {
        return $this->ledgers()->latest('transaction_date')->latest('id')->value('balance_after') ?? 0;
    }

    public function getTotalPendingAttribute(): float
    {
        return $this->pendingWorks()->sum('total_value');
    }

    public function getTotalPaidAttribute(): float
    {
        return $this->works()->where('payment_status', 'pago')->sum('total_value');
    }

    public function getTypeLabel(): string
    {
        return match ($this->type) {
            'tratorista' => 'Tratorista',
            'motorista' => 'Motorista',
            'diarista' => 'Diarista',
            'tecnico' => 'Técnico',
            'consultor' => 'Consultor',
            'outro' => 'Outro',
            default => ucfirst($this->type),
        };
    }

    public function scopeActive($query)
    {
        return $query->where('status', true);
    }
}
