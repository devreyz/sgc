<?php

namespace App\Filament\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

trait TenantScoped
{
    /**
     * Aplicar filtro de tenant ao query builder do recurso.
     * 
     * Adicione este trait a qualquer Resource do Filament que precise ser filtrado por tenant.
     * O trait automaticamente adiciona o filtro usando o tenant_id da sessão.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        
        // Super admin vê todos os registros
        if (Auth::user()?->hasRole('super_admin')) {
            return $query;
        }
        
        // Aplicar filtro de tenant para usuários normais
        $tenantId = session('tenant_id');
        
        if ($tenantId) {
            return $query->where('tenant_id', $tenantId);
        }
        
        // Se não tiver tenant na sessão, não retornar nada
        return $query->whereRaw('1 = 0');
    }
}
