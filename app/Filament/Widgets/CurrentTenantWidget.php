<?php

namespace App\Filament\Widgets;

use App\Models\Tenant;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class CurrentTenantWidget extends Widget
{
    protected static string $view = 'filament.widgets.current-tenant-widget';

    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = -2; // Aparecer no topo

    /**
     * Determina se o widget deve ser exibido
     */
    public static function canView(): bool
    {
        $user = Auth::user();
        
        // Sempre mostrar para usuários autenticados com tenant selecionado
        return $user && session('tenant_id');
    }

    /**
     * Obtém o tenant atual
     */
    public function getCurrentTenant(): ?Tenant
    {
        $tenantId = session('tenant_id');
        
        if (!$tenantId) {
            return null;
        }

        return Tenant::find($tenantId);
    }

    /**
     * Verifica se o usuário é admin do tenant
     */
    public function isAdminOfCurrentTenant(): bool
    {
       $user = Auth::user();
        
        if (!$user) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        $tenantId = session('tenant_id');
        
        if (!$tenantId) {
            return false;
        }

        return $user->isTenantAdmin($tenantId);
    }
}
