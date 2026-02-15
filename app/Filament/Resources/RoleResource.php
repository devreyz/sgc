<?php

namespace App\Filament\Resources;

use BezhanSalleh\FilamentShield\Resources\RoleResource as ShieldRoleResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Customização do RoleResource do Shield para:
 * 1. Ocultar role 'super_admin' do painel admin
 * 2. Apenas super admins podem ver todas as roles
 */
class RoleResource extends ShieldRoleResource
{
    /**
     * Sobrescrever query para filtrar roles no painel admin.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        
        // Super admin vê todas as roles
        if (Auth::user()?->hasRole('super_admin')) {
            return $query;
        }
        
        // Admins de organização NÃO podem ver super_admin
        return $query->where('name', '!=', 'super_admin');
    }
}
