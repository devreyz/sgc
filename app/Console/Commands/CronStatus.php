<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CronStatus extends Command
{
    protected $signature = 'system:cron-status';
    protected $description = 'Mostra a ultima execucao confirmada do scheduler e o estado da fila de notificacoes';

    public function handle(): int
    {
        $heartbeat = Cache::get('system:cron:last_heartbeat');
        $this->line('Ultimo heartbeat do scheduler: '.($heartbeat ?: 'ainda nao registrado'));

        if (! Schema::hasTable('jobs')) {
            $this->warn('Tabela jobs nao encontrada.');
            return self::SUCCESS;
        }

        $waiting = DB::table('jobs')->where('queue', 'notifications')->count();
        $failed = Schema::hasTable('failed_jobs')
            ? DB::table('failed_jobs')->where('queue', 'notifications')->count()
            : 0;

        $this->line("Fila notifications: {$waiting} aguardando; {$failed} falha(s).");

        return self::SUCCESS;
    }
}
