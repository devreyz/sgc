<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use BezhanSalleh\FilamentShield\Resources\RoleResource;
use Filament\Facades\Filament;

class ShieldCustomizationServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Customizar o Shield para filtrar roles por tenant no painel admin
        if (app()->runningInConsole()) {
            return;
        }

        // Hook para modificar query do RoleResource (se a API do Resource suportar)
        if (method_exists(RoleResource::class, 'eloquentQuery') || (is_callable([RoleResource::class, 'hasMacro']) && RoleResource::hasMacro('eloquentQuery'))) {
            RoleResource::eloquentQuery(function ($query) {
                $currentPanel = Filament::getCurrentPanel();
                
                // No painel admin, filtrar por tenant
                if ($currentPanel && $currentPanel->getId() === 'admin') {
                    $tenantId = session('tenant_id');
                    
                    if ($tenantId) {
                        // Mostrar apenas roles da organização atual
                        $query->where('tenant_id', $tenantId)
                            // E excluir super_admin
                            ->where('name', '!=', 'super_admin');
                    } else {
                        // Se não tem tenant selecionado, não mostrar nada
                        $query->whereRaw('1 = 0');
                    }
                }
                // No painel super-admin, mostrar todas
                elseif ($currentPanel && $currentPanel->getId() === 'super-admin') {
                    // Mostrar todas as roles (incluindo as globais e de todos os tenants)
                    // Nenhum filtro aplicado
                }
                
                return $query;
            });
        } else {
            // Fallback: se a API do RoleResource não expor eloquentQuery, aplicamos
            // um filtro via Filament::serving para setar um listener global que
            // impedirá listagem indevida quando não houver tenant selecionado.
            Filament::serving(function () {
                $currentPanel = Filament::getCurrentPanel();
                if ($currentPanel && $currentPanel->getId() === 'admin') {
                    // forçar que, sem tenant, o admin não veja roles
                    if (! session('tenant_id')) {
                        // definir resposta vazia através de uma flag na sessão usada em recursos
                        session()->put('filament_admin_roles_block', true);
                    }
                }
            });
        }
    }
}
