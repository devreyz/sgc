<?php

namespace App\Console\Commands;

use App\Models\Associate;
use App\Models\Service;
use App\Models\ServiceProvider;
use App\Models\ServiceProviderService;
use App\Models\SalesProject;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantIdentityService;
use Illuminate\Console\Command;

class CheckTenantData extends Command
{
    protected $signature = 'tenant:check {tenant_id?}';
    protected $description = 'Verificar dados de um tenant específico para debugging';

    public function handle()
    {
        $tenantId = $this->argument('tenant_id');

        if (!$tenantId) {
            $tenants = Tenant::all();
            $this->info('Tenants disponíveis:');
            foreach ($tenants as $tenant) {
                $this->line("  [{$tenant->id}] {$tenant->name} ({$tenant->slug})");
            }
            $tenantId = $this->ask('Qual tenant deseja verificar? (ID)');
        }

        $tenant = Tenant::find($tenantId);
        if (!$tenant) {
            $this->error("Tenant #{$tenantId} não encontrado!");
            return 1;
        }

        $this->info("========================================");
        $this->info("Diagnóstico do Tenant: {$tenant->name}");
        $this->info("========================================\n");

        // Users
        $users = User::whereHas('tenants', fn($q) => $q->where('tenants.id', $tenantId))->get();
        $this->info("👥 Usuários: " . $users->count());
        foreach ($users as $user) {
            $userName = app(TenantIdentityService::class)->displayName((int) $tenantId, (int) $user->id);
            $this->line("   - {$userName} ({$user->email})");
            $roles = $user->roles->pluck('name')->join(', ');
            $this->line("     Roles: {$roles}");
        }
        $this->newLine();

        // Service Providers
        $providers = ServiceProvider::where('tenant_id', $tenantId)->get();
        $this->info("🛠️  Prestadores de Serviço: " . $providers->count());
        foreach ($providers as $provider) {
            $userName = app(TenantIdentityService::class)->displayName((int) $tenantId, (int) $provider->user_id);
            $this->line("   - #{$provider->id} {$provider->name} (Usuário: {$userName})");
            
            // Serviços do prestador
            $providerServices = ServiceProviderService::where('service_provider_id', $provider->id)
                ->where('tenant_id', $tenantId)
                ->where('status', true)
                ->with('service')
                ->get();
            
            $this->line("     Serviços vinculados: " . $providerServices->count());
            foreach ($providerServices as $ps) {
                $serviceName = $ps->service->name ?? '[SERVIÇO INEXISTENTE]';
                $serviceStatus = $ps->service?->status ? '✓' : '✗';
                $this->line("       • {$serviceName} {$serviceStatus}");
            }
        }
        $this->newLine();

        // Services
        $services = Service::where('tenant_id', $tenantId)->get();
        $this->info("📋 Serviços Cadastrados: " . $services->count());
        foreach ($services as $service) {
            $status = $service->status ? '✓ Ativo' : '✗ Inativo';
            $this->line("   - #{$service->id} {$service->name} ({$status})");
        }
        $this->newLine();

        // ServiceProviderServices (join table)
        $sps = ServiceProviderService::where('tenant_id', $tenantId)->get();
        $this->info("🔗 Associações Prestador ↔ Serviço: " . $sps->count());
        foreach ($sps as $item) {
            $providerName = ServiceProvider::withoutGlobalScopes()->find($item->service_provider_id)?->name ?? 'N/A';
            $serviceName = Service::withoutGlobalScopes()->find($item->service_id)?->name ?? 'N/A';
            $status = $item->status ? '✓' : '✗';
            $this->line("   - {$providerName} → {$serviceName} {$status}");
            
            // Verificar se ambos têm o tenant_id correto
            $provider = ServiceProvider::withoutGlobalScopes()->find($item->service_provider_id);
            $service = Service::withoutGlobalScopes()->find($item->service_id);
            
            $warnings = [];
            if ($provider && $provider->tenant_id != $tenantId) {
                $warnings[] = "⚠️ Prestador está em outro tenant (#{$provider->tenant_id})";
            }
            if ($service && $service->tenant_id != $tenantId) {
                $warnings[] = "⚠️ Serviço está em outro tenant (#{$service->tenant_id})";
            }
            if ($item->tenant_id != $tenantId) {
                $warnings[] = "⚠️ Associação está em outro tenant (#{$item->tenant_id})";
            }
            
            foreach ($warnings as $warning) {
                $this->warn("     {$warning}");
            }
        }
        $this->newLine();

        // Associates
        $associates = Associate::where('tenant_id', $tenantId)->get();
        $this->info("👨‍🌾 Associados: " . $associates->count());
        foreach ($associates as $assoc) {
            $userName = $assoc->display_name;
            $this->line("   - #{$assoc->id} {$userName} (Usuário: {$userName})");
        }
        $this->newLine();

        // Sales Projects
        $projects = SalesProject::where('tenant_id', $tenantId)->get();
        $this->info("📦 Projetos de Venda: " . $projects->count());
        foreach ($projects as $project) {
            $status = $project->status->getLabel();
            $this->line("   - #{$project->id} {$project->title} ({$status})");
        }
        $this->newLine();

        $this->info("========================================");
        $this->info("Diagnóstico completo!");
        $this->info("========================================");

        return 0;
    }
}
