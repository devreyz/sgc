<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PurgeLegacyWebPushFailures extends Command
{
    protected $signature = 'notifications:purge-legacy-webpush {--force : Apaga definitivamente as falhas legadas de Web Push}';
    protected $description = 'Remove somente jobs falhos da antiga entrega Web Push de navegador';

    public function handle(): int
    {
        if (! Schema::hasTable('failed_jobs')) {
            $this->warn('A tabela failed_jobs nao existe neste ambiente.');
            return self::SUCCESS;
        }

        $jobs = DB::table('failed_jobs')->where('payload', 'like', '%SendWebPushNotification%');
        $count = $jobs->count();

        if ($count === 0) {
            $this->info('Nenhuma falha legada de Web Push encontrada.');
            return self::SUCCESS;
        }

        if (! $this->option('force')) {
            $this->warn("Foram encontradas {$count} falha(s) legada(s) de Web Push.");
            $this->line('Revise a quantidade e execute novamente com --force para apagar somente essas falhas.');
            return self::SUCCESS;
        }

        $deleted = $jobs->delete();
        $this->info("{$deleted} falha(s) legada(s) de Web Push removida(s).");

        return self::SUCCESS;
    }
}
