<?php

namespace App\Filament\Pages;

use App\Models\Tenant;
use App\Services\TenantResolver;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class TenantSelector extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static string $view = 'filament.pages.tenant-selector';

    protected static ?string $navigationLabel = 'Selecionar Organização';

    protected static ?string $title = 'Selecionar Organização';

    protected static ?string $navigationGroup = 'Sistema';

    protected static ?int $navigationSort = 999;

    /**
     * Verifica se o usuário pode ver esta página
     */
    public static function canAccess(): bool
    {
        $user = Auth::user();
        
        // Super admin sempre pode ver
        if ($user && $user->isSuperAdmin()) {
            return true;
        }

        // Usuários com múltiplos tenants podem ver
        if ($user && $user->tenants()->count() > 1) {
            return true;
        }

        return false;
    }

    /**
     * Retorna tenants disponíveis para o usuário
     */
    public function getAvailableTenants()
    {
        $user = Auth::user();

        if ($user->isSuperAdmin()) {
            return Tenant::all();
        }

        return $user->tenants;
    }

    /**
     * Retorna o tenant atual
     */
    public function getCurrentTenant()
    {
        $tenantId = session('tenant_id');
        
        if ($tenantId) {
            return Tenant::find($tenantId);
        }

        return null;
    }

    /**
     * Troca o tenant ativo
     */
    public function switchTenant(?int $tenantId)
    {
        if (!$tenantId) {
            Notification::make()
                ->danger()
                ->title('Erro')
                ->body('Selecione uma organização.')
                ->send();

            return;
        }

        $user = Auth::user();

        // Valida se o usuário tem acesso ao tenant
        if (!$user->isSuperAdmin()) {
            if (!$user->tenants()->where('id', $tenantId)->exists()) {
                Notification::make()
                    ->danger()
                    ->title('Acesso Negado')
                    ->body('Você não tem acesso a esta organização.')
                    ->send();

                return;
            }
        }

        // Alterna o tenant
        app(TenantResolver::class)->setTenant($tenantId);

        Notification::make()
            ->success()
            ->title('Organização Alterada')
            ->body('Você está agora em: ' . Tenant::find($tenantId)->name)
            ->send();

        // Redireciona para o dashboard
        return redirect()->route('filament.admin.pages.dashboard');
    }

    /**
     * Limpa o tenant ativo (somente super admin)
     */
    public function clearTenant()
    {
        $user = Auth::user();

        if (!$user->isSuperAdmin()) {
            Notification::make()
                ->danger()
                ->title('Acesso Negado')
                ->body('Apenas super administradores podem limpar o tenant.')
                ->send();

            return;
        }

        session()->forget('tenant_id');

        Notification::make()
            ->info()
            ->title('Tenant Limpo')
            ->body('Nenhuma organização está ativa no momento.')
            ->send();
    }
}
