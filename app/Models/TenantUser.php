<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * TenantUser — Vínculo imutável entre User e Tenant (Organização).
 *
 * REGRAS DE INTEGRIDADE:
 * 1. Este registro NUNCA pode ser deletado (nem soft delete).
 * 2. O `id` é imutável e referenciado indiretamente pelo histórico de negócio.
 * 3. O vínculo pode ser DESATIVADO (status = false), nunca removido.
 * 4. `user_id` pode ser alterado APENAS pelo fluxo de troca de email (EmailSwapService).
 * 5. `tenant_id` NUNCA pode ser alterado.
 *
 * Campos editáveis pelo Admin da organização:
 * - status (ativar/desativar)
 * - roles (JSON com roles por tenant)
 * - is_admin (flag de administrador)
 * - tenant_name (nome de exibição no tenant)
 * - tenant_password (senha específica do tenant)
 * - notes (observações)
 *
 * Campos editáveis APENAS pelo sistema (EmailSwapService):
 * - user_id (troca de email = troca de user vinculado)
 *
 * Campos imutáveis:
 * - id
 * - tenant_id
 */
class TenantUser extends Model
{
    use LogsActivity;

    protected $table = 'tenant_user';

    /**
     * Desabilita mass assignment para proteger campos sensíveis.
     * Cada campo deve ser atualizado conscientemente.
     */
    protected $guarded = ['id', 'tenant_id'];

    protected $fillable = [
        'tenant_id',
        'user_id',
        'is_admin',
        'roles',
        'status',
        'tenant_name',
        'tenant_password',
        'deactivated_at',
        'deactivated_by',
        'notes',
        'email_history',
    ];

    protected function casts(): array
    {
        return [
            'is_admin' => 'boolean',
            'status' => 'boolean',
            'roles' => 'array',
            'email_history' => 'array',
            'tenant_password' => 'hashed',
            'deactivated_at' => 'datetime',
        ];
    }

    // ─── Proteções de integridade ────────────────────────────────

    protected static function booted(): void
    {
        // BLOQUEAR DELEÇÃO: Vínculos NUNCA podem ser apagados
        static::deleting(function (TenantUser $tenantUser) {
            throw new \RuntimeException(
                'Vínculos de organização não podem ser excluídos. Use desativação (status = false).'
            );
        });

        // BLOQUEAR alteração de tenant_id
        static::updating(function (TenantUser $tenantUser) {
            if ($tenantUser->isDirty('tenant_id')) {
                throw new \RuntimeException(
                    'O tenant_id de um vínculo não pode ser alterado. Este campo é imutável.'
                );
            }
        });

        // Registrar tenant_id no log de atividade ao criar
        static::creating(function (TenantUser $tenantUser) {
            // Garante que tenant_id está definido
            if (empty($tenantUser->tenant_id)) {
                throw new \RuntimeException('tenant_id é obrigatório para criar um vínculo.');
            }
        });
    }

    // ─── Activity Log ────────────────────────────────────────────

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'is_admin', 'roles', 'tenant_name', 'deactivated_at', 'deactivated_by', 'notes'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('tenant_user');
    }

    public function tapActivity(\Spatie\Activitylog\Contracts\Activity $activity): void
    {
        $activity->properties = $activity->properties->merge([
            'tenant_id' => $this->tenant_id,
        ]);
    }

    // ─── Relationships ───────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function deactivatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deactivated_by');
    }

    // ─── Scopes ──────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    public function scopeInactive($query)
    {
        return $query->where('status', false);
    }

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeAdmins($query)
    {
        return $query->where('is_admin', true);
    }

    // ─── Helper Methods ──────────────────────────────────────────

    /**
     * Desativa o vínculo (nunca deleta).
     */
    public function deactivate(?int $deactivatedBy = null): void
    {
        $this->update([
            'status' => false,
            'deactivated_at' => now(),
            'deactivated_by' => $deactivatedBy ?? auth()->id(),
        ]);
    }

    /**
     * Reativa o vínculo.
     */
    public function activate(): void
    {
        $this->update([
            'status' => true,
            'deactivated_at' => null,
            'deactivated_by' => null,
        ]);
    }

    /**
     * Verifica se o vínculo possui uma role específica.
     */
    public function hasRole(string $role): bool
    {
        $roles = $this->roles ?? [];
        return in_array($role, $roles);
    }

    /**
     * Retorna o nome de exibição (tenant_name ou nome global do user).
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->tenant_name ?? $this->user?->getRawOriginal('name') ?? 'N/A';
    }

    /**
     * Retorna o email do user vinculado.
     */
    public function getEmailAttribute(): string
    {
        return $this->user?->email ?? 'N/A';
    }

    /**
     * Retorna o associate vinculado a este user neste tenant (se houver).
     */
    public function getAssociateAttribute(): ?Associate
    {
        return Associate::withoutGlobalScopes()
            ->where('user_id', $this->user_id)
            ->where('tenant_id', $this->tenant_id)
            ->first();
    }

    /**
     * Retorna o service provider vinculado a este user neste tenant (se houver).
     */
    public function getServiceProviderAttribute(): ?ServiceProvider
    {
        return ServiceProvider::withoutGlobalScopes()
            ->where('user_id', $this->user_id)
            ->where('tenant_id', $this->tenant_id)
            ->first();
    }
}
