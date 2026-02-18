<?php

namespace App\Traits;

use App\Models\Tenant;
use App\Services\TenantResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

trait BelongsToTenant
{
    /**
     * Boot the BelongsToTenant trait.
     */
    protected static function bootBelongsToTenant(): void
    {
        // Apply global scope to filter by tenant automatically
        static::addGlobalScope('tenant', function (Builder $builder) {
            // Get current tenant ID from session
            $tenantId = session('tenant_id');

            // IMPORTANTE: Mesmo super_admin deve respeitar o tenant da sessão quando houver um selecionado
            // Super admin só vê todos os dados quando NÃO há tenant na sessão
            // Isso garante que ao trabalhar em um painel de tenant específico, 
            // apenas dados daquele tenant sejam manipulados
            
            // Apply tenant filter if tenant is set
            if ($tenantId) {
                $builder->where(static::getQualifiedTenantColumn(), $tenantId);
            }
        });

        // Auto-inject tenant_id when creating new records
        static::creating(function (Model $model) {
            // Skip if tenant_id is already set
            if ($model->getAttribute('tenant_id')) {
                return;
            }

            // Skip if user is not authenticated
            if (!Auth::check()) {
                return;
            }

            // Get tenant from resolver
            $tenantResolver = app(TenantResolver::class);
            $tenantId = $tenantResolver->resolve();

            // Set tenant_id
            if ($tenantId) {
                $model->setAttribute('tenant_id', $tenantId);
            } else {
                // Block creation if no valid tenant
                throw new \Exception('Nenhum tenant válido encontrado. Não é possível criar registros sem um tenant ativo.');
            }
        });

        // Prevent updates to records from other tenants
        static::updating(function (Model $model) {
            $currentTenantId = session('tenant_id');
            $modelTenantId = $model->getAttribute('tenant_id');

            // Se há tenant selecionado na sessão, validar que não está tentando editar de outro tenant
            // Isso se aplica até para super_admin quando trabalhando em contexto de tenant
            if ($currentTenantId && $modelTenantId && $currentTenantId != $modelTenantId) {
                throw new \Exception('Você não tem permissão para atualizar registros de outra organização.');
            }
        });
    }

    /**
     * Get the tenant that owns the model.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the qualified tenant column name.
     */
    public static function getQualifiedTenantColumn(): string
    {
        return (new static())->getTable() . '.tenant_id';
    }

    /**
     * Scope query to specific tenant.
     */
    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where(static::getQualifiedTenantColumn(), $tenantId);
    }

    /**
     * Scope query without tenant restriction (use with caution).
     */
    public function scopeWithoutTenant(Builder $query): Builder
    {
        return $query->withoutGlobalScope('tenant');
    }

    /**
     * Check if model belongs to current tenant.
     */
    public function belongsToCurrentTenant(): bool
    {
        $currentTenantId = session('tenant_id');
        
        if (!$currentTenantId) {
            return false;
        }

        return $this->getAttribute('tenant_id') == $currentTenantId;
    }

    /**
     * Check if model belongs to a specific tenant.
     */
    public function belongsToTenant(int $tenantId): bool
    {
        return $this->getAttribute('tenant_id') == $tenantId;
    }

    /**
     * Injeta tenant_id automaticamente nos logs de atividade.
     * Funciona quando o model também usa LogsActivity do Spatie.
     */
    public function tapActivity(\Spatie\Activitylog\Contracts\Activity $activity, string $eventName = ''): void
    {
        $tenantId = $this->getAttribute('tenant_id') ?? session('tenant_id');
        
        if ($tenantId) {
            // Injeta na coluna direta (se existir)
            $activity->tenant_id = $tenantId;
            
            // Também nas properties para retrocompatibilidade
            $properties = $activity->properties ?? collect();
            $activity->properties = $properties->merge([
                'tenant_id' => $tenantId,
            ]);
        }
    }
}
