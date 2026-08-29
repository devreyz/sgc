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

        $jobs = DB::table('jobs')->where('queue', 'notifications');
        $now = now()->timestamp;
        $retryAfter = (int) config('queue.connections.database.retry_after', 180);
        $total = (clone $jobs)->count();
        $waiting = (clone $jobs)->whereNull('reserved_at')->where('available_at', '<=', $now)->count();
        $scheduled = (clone $jobs)->whereNull('reserved_at')->where('available_at', '>', $now)->count();
        $processing = (clone $jobs)->whereNotNull('reserved_at')->where('reserved_at', '>', $now - $retryAfter)->count();
        $stale = (clone $jobs)->whereNotNull('reserved_at')->where('reserved_at', '<=', $now - $retryAfter)->count();
        $failed = Schema::hasTable('failed_jobs')
            ? DB::table('failed_jobs')->where('queue', 'notifications')->count()
            : 0;

        $this->newLine();
        $this->info('Notificações');
        $this->table(['Item', 'Valor'], [
            ['Total', $total],
            ['Disponíveis', $waiting],
            ['Aguardando nova tentativa', $scheduled],
            ['Em processamento', $processing],
            ['Atrasadas/recuperáveis', $stale],
            ['Falhas definitivas', $failed],
            ['Última verificação do worker', Cache::get('system:notifications:last_worker_check', 'nunca')],
            ['Última tarefa iniciada', Cache::get('system:notifications:last_job_started', 'nunca')],
            ['Última tarefa concluída', Cache::get('system:notifications:last_job_processed', 'nunca')],
            ['Total processado desde o último reset do cache', Cache::get('system:notifications:processed_total', 0)],
        ]);

        return self::SUCCESS;
    }
}
