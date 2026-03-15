<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('teslog:refresh-tokens')->everyFifteenMinutes();
Schedule::command('teslog:check-health')->everyFifteenMinutes();
Schedule::command('teslog:record-battery-health')->dailyAt('03:00');
Schedule::command('horizon:snapshot')->everyFiveMinutes();
