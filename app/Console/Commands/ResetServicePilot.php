<?php

namespace App\Console\Commands;

use App\Services\Services\ServicePilotResetService;
use Illuminate\Console\Command;

class ResetServicePilot extends Command
{
    protected $signature = 'services:pilot-reset {--dry-run : Apenas exibe o preflight} {--force : Executa a limpeza comprovada} {--token= : Token adicional obrigatório em produção}';

    protected $description = 'Diagnostica ou remove exclusivamente fatos do módulo piloto de serviços';

    public function handle(ServicePilotResetService $service): int
    {
        $report = $service->preflight();
        $this->table(['Alvo comprovado', 'Quantidade/amostra'], collect($report)->map(fn ($value, $key) => [$key, is_array($value) ? implode(', ', $value) : (string) $value])->values()->all());
        if (! $this->option('force')) {
            $this->info('Dry-run concluído. Nada foi alterado.');

            return self::SUCCESS;
        }
        if (app()->environment('production')) {
            $expected = (string) config('services-module.pilot_reset_token');
            $provided = (string) $this->option('token');
            if ($expected === '' || ! hash_equals($expected, $provided)) {
                $this->error('Produção exige SERVICES_PILOT_RESET_TOKEN válido e --token.');

                return self::FAILURE;
            }
        }
        $service->reset();
        $this->warn('Fatos comprovadamente originados no piloto foram removidos. Users, tenants, memberships, associados, prestadores e contas não foram alterados.');

        return self::SUCCESS;
    }
}
