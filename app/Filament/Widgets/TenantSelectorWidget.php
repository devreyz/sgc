<?php

namespace App\Filament\Widgets;

use App\Services\TenantResolver;
use Filament\Widgets\Widget;
use Livewire\Component;
use Illuminate\Support\Facades\Auth;

class TenantSelectorWidget extends Widget
{
    protected static string $view = 'filament.widgets.tenant-selector';

    protected int | string | array $columnSpan = 'full';

    public ?int $selectedTenant = null;
    public array $availableTenants = [];
    public ?string $currentTenantName = null;
    public bool $hasMultipleTenants = false;

    public function mount(): void
    {
        $tenantResolver = app(TenantResolver::class);
        
        // Get available tenants
        $this->availableTenants = $tenantResolver->getAvailableTenants();
        $this->hasMultipleTenants = count($this->availableTenants) > 1;
        
        // Get current tenant
        $currentTenant = $tenantResolver->current();
        if ($currentTenant) {
            $this->selectedTenant = $currentTenant->id;
            $this->currentTenantName = $currentTenant->name;
        }
    }

    public function switchTenant(): void
    {
        if (!$this->selectedTenant) {
            $this->addError('selectedTenant', 'Por favor, selecione uma organização.');
            return;
        }

        try {
            $tenantResolver = app(TenantResolver::class);
            $tenantResolver->setTenant($this->selectedTenant);
            
            // Refresh page to apply new tenant context
            $this->redirect(request()->header('Referer') ?: '/admin');
        } catch (\Exception $e) {
            $this->addError('selectedTenant', $e->getMessage());
        }
    }

    public static function canView(): bool
    {
        // Don't show in super admin panel
        if (request()->is('super-admin*')) {
            return false;
        }

        return Auth::check() && !Auth::user()->isSuperAdmin();
    }
}
