<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('queue:work', [
    '--queue' => 'notifications',
    '--stop-when-empty' => true,
    '--max-time' => 50,
    '--tries' => 3,
    '--timeout' => 45,
    '--memory' => 128,
])->everyMinute()->withoutOverlapping(3);

Schedule::call(function (): void {
    Cache::put('system:cron:last_heartbeat', now()->toIso8601String(), now()->addDays(2));
})->everyMinute()->name('cron-heartbeat');

Schedule::command('drive:sync-documents')
    ->everyFifteenMinutes()
    ->withoutOverlapping(14);

Schedule::command('queue:work', [
    '--queue' => 'documents',
    '--stop-when-empty' => true,
    '--max-jobs' => 3,
    '--max-time' => 55,
    '--tries' => 3,
    '--timeout' => 120,
    '--memory' => 128,
])->everyMinute()->withoutOverlapping(3);

Schedule::command('queue:work', [
    '--queue' => 'default',
    '--stop-when-empty' => true,
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
