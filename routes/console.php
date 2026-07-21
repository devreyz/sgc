<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('queue:work', [
    '--queue' => 'documents,default',
    '--stop-when-empty' => true,
    '--max-time' => 240,
    '--tries' => 3,
    '--timeout' => 120,
    '--memory' => 128,
])->everyFiveMinutes()->withoutOverlapping(10);

Schedule::command('queue:prune-failed', [
    '--hours' => 720,
])->dailyAt('03:30')->withoutOverlapping();
