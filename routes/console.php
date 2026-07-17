<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


Schedule::command('lifecycle:reconcile --apply')
    ->dailyAt('03:00')
    ->withoutOverlapping();

// Enabled 2026-07-17: campaigns:send now runs automatically via the scheduler.
Schedule::command('campaigns:send --batch=50')
    ->everyMinute()
    ->withoutOverlapping(10);
//    ->runInBackground();

// Daily statistics snapshot. VPS cron required: * * * * * php /path/to/artisan schedule:run >> /dev/null 2>&1
Schedule::command('stats:snapshot')
    ->dailyAt('02:00')
    ->withoutOverlapping();

Schedule::command('heat:recalculate')->dailyAt('02:30');
Schedule::command('firstaid:recalculate')->dailyAt('02:30');
