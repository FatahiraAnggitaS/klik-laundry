<?php

use App\Jobs\CleanupExpiredProofsJob;
use App\Jobs\ExpireDriverOffersJob;
use App\Jobs\ExpirePaymentAttemptsJob;
use App\Jobs\ExpirePiiAccessGrantsJob;
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

Schedule::job(new CleanupExpiredProofsJob)
    ->name('cleanup-expired-private-proofs')
    ->dailyAt('02:30')
    ->withoutOverlapping(30)
    ->onOneServer();

Schedule::job(new ExpirePiiAccessGrantsJob)
    ->name('expire-pii-access-grants')
    ->everyMinute()
    ->withoutOverlapping(5)
    ->onOneServer();

Schedule::command(sprintf(
    'queue:monitor %s:%s --max=%d',
    config('operations.queue_connection'),
    config('operations.queue_name'),
    config('operations.queue_max_jobs'),
))->name('monitor-queue-backlog')->everyMinute()->withoutOverlapping(5)->onOneServer();

Schedule::command('operations:check-backup')
    ->name('check-backup-freshness')
    ->hourly()
    ->withoutOverlapping(10)
    ->onOneServer();
