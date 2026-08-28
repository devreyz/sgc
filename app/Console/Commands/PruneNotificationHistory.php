<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PruneNotificationHistory extends Command
{
    protected $signature = 'notifications:prune-history
        {--read-days= : Dias de retenção para notificações lidas}
        {--unread-days= : Dias de retenção para notificações não lidas}';

    protected $description = 'Remove somente notificações antigas após o período de retenção configurado';

    public function handle(): int
    {
        if (! Schema::hasTable('notifications')) {
            return self::SUCCESS;
        }

        $readDays = max(1, (int) ($this->option('read-days') ?: config('notifications.retention.read_days', 365)));
        $unreadDays = max($readDays, (int) ($this->option('unread-days') ?: config('notifications.retention.unread_days', 730)));

        $readDeleted = DB::table('notifications')
            ->whereNotNull('read_at')
            ->where('created_at', '<', now()->subDays($readDays))
            ->delete();
        $unreadDeleted = DB::table('notifications')
            ->whereNull('read_at')
            ->where('created_at', '<', now()->subDays($unreadDays))
            ->delete();

        $this->info("{$readDeleted} lida(s) e {$unreadDeleted} não lida(s) removida(s) conforme a retenção.");

        return self::SUCCESS;
    }
}
