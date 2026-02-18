<?php

namespace App\Traits;

use Spatie\Activitylog\Contracts\Activity;

/**
 * LogsTenantActivity — Garante que tenant_id seja registrado em todos os logs.
 *
 * USE este trait em TODOS os models que herdam LogsActivity e possuem tenant_id.
 *
 * Funcionamento:
 * - Intercepta a criação do log via tapActivity()
 * - Injeta tenant_id no log a partir do model OU da sessão
 * - Garante que Admin só veja logs da própria organização
 * - Super Admin veja logs globais
 */
trait LogsTenantActivity
{
    /**
     * Injeta tenant_id automaticamente em cada entrada de log.
     */
    public function tapActivity(Activity $activity, string $eventName): void
    {
        // Tenta pegar tenant_id do próprio model
        $tenantId = null;

        if (method_exists($this, 'getAttribute') && $this->getAttribute('tenant_id')) {
            $tenantId = $this->getAttribute('tenant_id');
        }

        // Fallback: tenta pegar da sessão
        if (!$tenantId) {
            $tenantId = session('tenant_id');
        }

        // Injeta tenant_id diretamente na coluna (se existir na tabela activity_log)
        if ($tenantId && isset($activity->tenant_id)) {
            $activity->tenant_id = $tenantId;
        }

        // Também salva nas properties para redundância e queries
        $properties = $activity->properties ?? collect();
        $activity->properties = $properties->merge([
            'tenant_id' => $tenantId,
            'session_tenant_id' => session('tenant_id'),
        ]);
    }
}
