<?php

namespace App\Console\Commands;

use App\Models\Service;
use App\Models\ServiceProviderService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixOrphanedTenantData extends Command
{
    protected $signature = 'tenant:fix-orphans {--dry-run : Apenas mostrar problemas sem corrigir}';

    protected $description = 'Identificar e corrigir dados órfãos entre tenants';

    public function handle()
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('🔍 MODO DRY-RUN: Apenas mostrando problemas, sem aplicar correções');
        } else {
            $this->info('⚠️  MODO CORREÇÃO: Problemas serão corrigidos automaticamente');
            if (! $this->confirm('Deseja continuar?')) {
                $this->info('Operação cancelada.');

                return 0;
            }
        }

        $this->newLine();
        $this->info('========================================');
        $this->info('🔎 Procurando Dados Órfãos...');
        $this->info('========================================');
        $this->newLine();

        $totalIssues = 0;
        $totalFixed = 0;

        // 1. ServiceProviderService com serviços de outro tenant
        $this->info('1️⃣  Verificando ServiceProviderService...');

        $orphanedSPS = DB::table('service_provider_services as sps')
            ->join('service_providers as sp', 'sps.service_provider_id', '=', 'sp.id')
            ->leftJoin('services as s', function ($join) {
                $join->on('sps.service_id', '=', 's.id')
                    ->whereColumn('sps.tenant_id', '=', 's.tenant_id');
            })
            ->whereNull('s.id') // Service não encontrado no mesmo tenant
            ->select(
                'sps.id as sps_id',
                'sps.tenant_id as sps_tenant_id',
                'sps.service_id',
                'sps.service_provider_id',
                'sp.name as provider_name',
                'sp.tenant_id as provider_tenant_id'
            )
            ->get();

        if ($orphanedSPS->isEmpty()) {
            $this->info('   ✅ Nenhum problema encontrado em ServiceProviderService');
        } else {
            $totalIssues += $orphanedSPS->count();
            $this->warn("   ⚠️  Encontrados {$orphanedSPS->count()} registros órfãos:");

            foreach ($orphanedSPS as $item) {
                // Tentar encontrar o serviço em qualquer tenant
                $service = Service::withoutGlobalScopes()->find($item->service_id);
                $serviceName = $service ? $service->name : 'SERVIÇO DELETADO';
                $serviceTenant = $service ? $service->tenant_id : 'N/A';

                $this->line("      • SPS #{$item->sps_id}: Prestador '{$item->provider_name}' (tenant #{$item->provider_tenant_id}) ".
                           "→ Serviço #{$item->service_id} '{$serviceName}' (tenant #{$serviceTenant})");

                if (! $dryRun) {
                    if (! $service) {
                        // Serviço não existe, deletar a associação
                        DB::table('service_provider_services')->where('id', $item->sps_id)->delete();
                        $this->info('         ✓ Associação deletada (serviço não existe)');
                        $totalFixed++;
                    } else {
                        // Serviço existe mas em outro tenant - sugerir ação manual
                        $this->warn("         ⚠️  AÇÃO MANUAL NECESSÁRIA: Serviço existe no tenant #{$serviceTenant}");
                        $this->warn('            Opções:');
                        $this->warn("            1. Deletar associação: DELETE FROM service_provider_services WHERE id = {$item->sps_id};");
                        $this->warn('            2. Mover prestador para tenant correto');
                        $this->warn("            3. Criar serviço duplicado no tenant #{$item->provider_tenant_id}");
                    }
                }
            }
        }

        $this->newLine();

        // 2. ServiceProviderService onde provider e sps têm tenants diferentes
        $this->info('2️⃣  Verificando consistência de tenant_id...');

        $inconsistentTenants = DB::table('service_provider_services as sps')
            ->join('service_providers as sp', 'sps.service_provider_id', '=', 'sp.id')
            ->whereColumn('sps.tenant_id', '!=', 'sp.tenant_id')
            ->select(
                'sps.id as sps_id',
                'sps.tenant_id as sps_tenant_id',
                'sp.id as provider_id',
                'sp.name as provider_name',
                'sp.tenant_id as provider_tenant_id'
            )
            ->get();

        if ($inconsistentTenants->isEmpty()) {
            $this->info('   ✅ Todos os tenant_id estão consistentes');
        } else {
            $totalIssues += $inconsistentTenants->count();
            $this->warn("   ⚠️  Encontradas {$inconsistentTenants->count()} inconsistências:");

            foreach ($inconsistentTenants as $item) {
                $this->line("      • SPS #{$item->sps_id} está no tenant #{$item->sps_tenant_id} ".
                           "mas prestador '{$item->provider_name}' está no tenant #{$item->provider_tenant_id}");

                if (! $dryRun) {
                    // Corrigir: atualizar tenant_id da associação para match com o provider
                    DB::table('service_provider_services')
                        ->where('id', $item->sps_id)
                        ->update(['tenant_id' => $item->provider_tenant_id]);
                    $this->info("         ✓ Tenant_id atualizado de {$item->sps_tenant_id} para {$item->provider_tenant_id}");
                    $totalFixed++;
                }
            }
        }

        $this->newLine();
        $this->info('========================================');

        if ($totalIssues === 0) {
            $this->info('✅ Nenhum problema encontrado! Banco de dados está consistente.');
        } else {
            if ($dryRun) {
                $this->warn("⚠️  {$totalIssues} problema(s) encontrado(s)");
                $this->info('Execute novamente sem --dry-run para aplicar correções automáticas');
            } else {
                $this->info("✅ {$totalFixed} problema(s) corrigido(s) automaticamente");
                if ($totalFixed < $totalIssues) {
                    $this->warn('⚠️  '.($totalIssues - $totalFixed).' problema(s) requerem ação manual');
                }
            }
        }

        $this->info('========================================');

        // Sugerir rodar tenant:check
        $this->newLine();
        $this->info('💡 Dica: Execute "php artisan tenant:check" para visualizar os dados após correção');

        return 0;
    }
}
