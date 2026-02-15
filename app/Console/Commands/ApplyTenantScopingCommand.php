<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ApplyTenantScopingCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'tenant:apply-scoping {--dry-run : Execute sem fazer alterações}';

    /**
     * The console command description.
     */
    protected $description = 'Aplica o trait TenantScoped a todos os resources do painel admin que têm tenant_id';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $resourcesPath = app_path('Filament/Resources');
        $resources = File::glob($resourcesPath . '/*Resource.php');
        
        // Resources que não precisam de tenant scoping ou têm lógica especial
        $excludedResources = [
            'UserResource.php', // Tem filtro customizado por relacionamento
        ];
        
        $modified = 0;
        $skipped = 0;
        $alreadyApplied = 0;
        
        $this->info('Processando resources do painel admin...');
        $this->newLine();
        
        foreach ($resources as $resourcePath) {
            $resourceName = basename($resourcePath);
            
            // Pular resources excluídos
            if (in_array($resourceName, $excludedResources)) {
                $this->warn("⊝ {$resourceName} - Pulado (tem lógica especial)");
                $skipped++;
                continue;
            }
            
            $content = File::get($resourcePath);
            
            // Verificar se já tem o trait
            if (str_contains($content, 'use TenantScoped')) {
                $this->line("✓ {$resourceName} - Já possui TenantScoped");
                $alreadyApplied++;
                continue;
            }
            
            // Verificar se o modelo tem tenant_id (checar import do model)
            preg_match('/use App\\\\Models\\\\(\w+);/', $content, $modelMatch);
            if (!$modelMatch) {
                $this->warn("⊝ {$resourceName} - Não foi possível identificar o modelo");
                $skipped++;
                continue;
            }
            
            $modelName = $modelMatch[1];
            $modelPath = app_path("Models/{$modelName}.php");
            
            if (!File::exists($modelPath)) {
                $this->warn("⊝ {$resourceName} - Modelo {$modelName} não encontrado");
                $skipped++;
                continue;
            }
            
            $modelContent = File::get($modelPath);
            
            // Verificar se o modelo usa BelongsToTenant trait ou tem tenant_id
            if (!str_contains($modelContent, 'BelongsToTenant') && !preg_match('/tenant_id/', $modelContent)) {
                $this->warn("⊝ {$resourceName} - Modelo {$modelName} não tem tenant_id");
                $skipped++;
                continue;
            }
            
            // Adicionar o import após os outros imports, antes da declaração da classe
            $lines = explode("\n", $content);
            $insertIndex = -1;
            $classIndex = -1;
            
            foreach ($lines as $index => $line) {
                if (preg_match('/^use /', $line)) {
                    $insertIndex = $index;
                }
                if (preg_match('/^class\s+\w+Resource\s+extends\s+Resource/', $line)) {
                    $classIndex = $index;
                    break;
                }
            }
            
            if ($insertIndex === -1 || $classIndex === -1) {
                $this->error("✗ {$resourceName} - Estrutura inesperada do arquivo");
                $skipped++;
                continue;
            }
            
            // Adicionar import após o último use
            if (!str_contains($content, 'use App\\Filament\\Traits\\TenantScoped;')) {
                array_splice($lines, $insertIndex + 1, 0, 'use App\\Filament\\Traits\\TenantScoped;');
                $classIndex++; // Ajustar índice da classe
            }
            
            // Adicionar o trait dentro da classe (após a abertura da classe)
            array_splice($lines, $classIndex + 2, 0, '    use TenantScoped;');
            
            $content = implode("\n", $lines);
            
            if (!$this->option('dry-run')) {
                File::put($resourcePath, $content);
                $this->info("✓ {$resourceName} - TenantScoped aplicado com sucesso!");
                $modified++;
            } else {
                $this->info("⊕ {$resourceName} - Seria modificado (dry-run)");
                $modified++;
            }
        }
        
        $this->newLine();
        $this->info('═══════════════════════════════════════════════════════════');
        $this->info("Resumo:");
        $this->info("• Modificados: {$modified}");
        $this->info("• Já tinham trait: {$alreadyApplied}");
        $this->info("• Pulados: {$skipped}");
        $this->info('═══════════════════════════════════════════════════════════');
        
        if ($this->option('dry-run')) {
            $this->warn('Executado em modo dry-run. Nenhuma alteração foi feita.');
            $this->info('Execute sem --dry-run para aplicar as alterações.');
        }
        
        return self::SUCCESS;
    }
}
