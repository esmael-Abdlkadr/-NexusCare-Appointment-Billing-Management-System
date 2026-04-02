<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('fees:assess-noshows')->everyMinute();
Schedule::command('fees:assess-overdue')->dailyAt('00:05');
Schedule::command('sync:incremental')->everyFifteenMinutes();
Schedule::command('records:purge')->monthly();
