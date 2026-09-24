<?php

use App\Jobs\ExpireDriverOffersJob;
use App\Jobs\ExpirePaymentAttemptsJob;
use App\Jobs\MonitorOrderIndicatorsJob;
use App\Jobs\RevokeExpiredProofAccessJob;
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

Schedule::job(new ExpireDriverOffersJob)
    ->name('expire-driver-offers')
    ->everyMinute()
    ->withoutOverlapping(10)
    ->onOneServer();

Schedule::job(new ExpirePaymentAttemptsJob)
    ->name('expire-payment-attempts')
    ->everyFiveMinutes()
    ->withoutOverlapping(10)
    ->onOneServer();

Schedule::job(new RevokeExpiredProofAccessJob)
    ->name('revoke-expired-proof-access')
    ->dailyAt('02:30')
    ->withoutOverlapping(30)
    ->onOneServer();
