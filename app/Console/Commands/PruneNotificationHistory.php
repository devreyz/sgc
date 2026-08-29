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

        $readIds = DB::table('notifications')
            ->whereNotNull('read_at')
            ->where('created_at', '<', now()->subDays($readDays))
            ->pluck('id');
        $unreadIds = DB::table('notifications')
            ->whereNull('read_at')
            ->where('created_at', '<', now()->subDays($unreadDays))
            ->pluck('id');

        $ids = $readIds->merge($unreadIds)->unique()->values();
        if ($ids->isNotEmpty() && Schema::hasTable('push_delivery_receipts')) {
            $ids->chunk(500)->each(fn ($chunk) => DB::table('push_delivery_receipts')
                ->whereIn('notification_id', $chunk->all())
                ->delete());
        }
        $readDeleted = $readIds->isEmpty() ? 0 : DB::table('notifications')->whereIn('id', $readIds)->delete();
        $unreadDeleted = $unreadIds->isEmpty() ? 0 : DB::table('notifications')->whereIn('id', $unreadIds)->delete();

        $this->info("{$readDeleted} lida(s) e {$unreadDeleted} não lida(s) removida(s) conforme a retenção.");

        return self::SUCCESS;
    }
}
