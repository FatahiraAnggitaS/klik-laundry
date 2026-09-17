<?php

use App\Jobs\MonitorOrderIndicatorsJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new MonitorOrderIndicatorsJob)
    ->name('monitor-order-indicators')
    ->everyFiveMinutes()
    ->withoutOverlapping(10)
    ->onOneServer();
