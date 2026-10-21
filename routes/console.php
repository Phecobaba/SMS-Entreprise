<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Offer expiry is a school-day rule: 00:15 means Africa/Lagos time.
Schedule::command('oakfield:lapse-expired-offers')->dailyAt('00:15')->timezone('Africa/Lagos');
Schedule::command('oakfield:sync-entrance-cbt-outcomes')->everyMinute()->timezone('Africa/Lagos');
Schedule::command('oakfield:attendance-alerts')->everyFiveMinutes()->timezone('Africa/Lagos');
