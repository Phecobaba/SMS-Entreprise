<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('oakfield:lapse-expired-offers')->dailyAt('00:15');
Schedule::command('oakfield:sync-entrance-cbt-outcomes')->everyMinute();
Schedule::command('oakfield:attendance-alerts')->weekdays()->dailyAt('16:00');
