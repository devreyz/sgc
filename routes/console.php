<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

$notificationWorker = Schedule::command('queue:work', [
    'database',
    '--stop-when-empty',
    '--queue' => 'notifications',
    '--max-time' => 50,
    '--tries' => 3,
    '--timeout' => 45,
    '--memory' => 128,
])->everyMinute()->withoutOverlapping(3);
$notificationWorker->before(function (): void {
    Cache::put('system:notifications:last_worker_check', now()->toIso8601String(), now()->addDays(2));
});

Schedule::call(function (): void {
    Cache::put('system:cron:last_heartbeat', now()->toIso8601String(), now()->addDays(2));
})->everyMinute()->name('cron-heartbeat');

// A sincronização é orientada a alterações. A varredura completa permanece
// disponível apenas pelo botão administrativo/comando drive:sync-documents.
Schedule::command('drive:compact-queue')
    ->everyFiveMinutes()
    ->withoutOverlapping(4);

Schedule::command('queue:work', [
    'database',
    '--stop-when-empty',
    '--queue' => 'documents',
    '--max-jobs' => 3,
    '--max-time' => 55,
    '--tries' => 3,
    '--timeout' => 120,
    '--memory' => 128,
])->everyMinute()->withoutOverlapping(3);

Schedule::command('queue:work', [
    'database',
    '--stop-when-empty',
    '--queue' => 'default',
    '--max-time' => 55,
    '--tries' => 3,
    '--timeout' => 45,
    '--memory' => 128,
])->everyFiveMinutes()->withoutOverlapping(3);

Schedule::command('queue:prune-failed', [
    '--hours' => 720,
])->dailyAt('03:30')->withoutOverlapping();

Schedule::command('notifications:prune-history')
    ->dailyAt('03:45')
    ->withoutOverlapping();
