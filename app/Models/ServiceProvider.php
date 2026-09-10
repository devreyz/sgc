<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceProvider extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'cpf',
        'rg',
        'phone',
        'email',
        'type',
        'provider_roles',
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
            'provider_roles' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'service_provider_services')
            ->withPivot(['provider_hourly_rate', 'provider_daily_rate', 'provider_unit_rate', 'status', 'notes'])
            ->withTimestamps()
            ->wherePivot('status', true);
    }

    public function allServices(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'service_provider_services')
            ->withPivot(['provider_hourly_rate', 'provider_daily_rate', 'provider_unit_rate', 'status', 'notes'])
            ->withTimestamps();
    }

    public function works(): HasMany
    {
        return $this->hasMany(ServiceProviderWork::class);
    }

    public function versionRates(): HasMany
    {
        return $this->hasMany(ServiceProviderVersionRate::class);
    }

    public function serviceObligations(): HasMany
    {
        return $this->hasMany(ServiceObligation::class);
    }

    public function payoutRequests(): HasMany
    {
        return $this->hasMany(ServicePayoutRequest::class);
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

    /**
     * Calcula o total pendente de recebimento (ordens concluídas mas não pagas)
     */
    public function getPendingReceivableAttribute(): float
    {
        return (float) \App\Models\ServiceOrder::where('service_provider_id', $this->id)
            ->whereIn('status', [
                \App\Enums\ServiceOrderStatus::AWAITING_PAYMENT,
                \App\Enums\ServiceOrderStatus::COMPLETED,
            ])
            ->get()
            ->sum('provider_remaining');
    }

    /**
     * Calcula saldo compartilhado quando usuário é associado e prestador
     * Por enquanto, o saldo é baseado apenas nos recebíveis como prestador
     * No futuro, pode incluir débitos de serviços solicitados como associado
     */
    public function getSharedWalletBalanceAttribute(): float
    {
        $balance = $this->pending_receivable;

        // Se usuário também é associado, pode ajustar saldo com débitos pendentes
        // (Por enquanto, mantém apenas recebíveis de prestação de serviço)
        if ($this->user && $this->user->associate) {
            // Futura lógica para debitar serviços solicitados como associado
            // $balance -= $this->user->associate->pending_payments;
        }

        return $balance;
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

    /**
     * Sync provider roles with user roles
     */
    public function syncRolesToUser(): void
    {
        if (!$this->user) {
            return;
        }

        // Lista de roles que são específicos de prestadores de serviço
        $providerRoles = $this->provider_roles ?? [];
        
        // Sempre mantém o role genérico 'service_provider'
        if (!in_array('service_provider', $providerRoles)) {
            $providerRoles[] = 'service_provider';
        }

        $membership = TenantUser::query()
            ->where('tenant_id', $this->tenant_id)
            ->where('user_id', $this->user_id)
            ->first();
        if (! $membership) {
            return;
        }
        $allProviderRoles = array_keys(self::getAvailableRoles());
        $allProviderRoles[] = 'service_provider';
        $current = array_values(array_diff($membership->roles ?? [], $allProviderRoles));
        $membership->update(['roles' => array_values(array_unique(array_merge($current, $providerRoles)))]);
    }

    /**
     * Get available provider role options
     */
    public static function getAvailableRoles(): array
    {
        return [
            'tratorista' => 'Tratorista',
            'motorista' => 'Motorista',
            'diarista' => 'Diarista',
            'tecnico' => 'Técnico',
            'registrador_entregas' => 'Registrador de Entregas',
            'visualizador_entregas' => 'Visualizador de Entregas',
        ];
    }
}
