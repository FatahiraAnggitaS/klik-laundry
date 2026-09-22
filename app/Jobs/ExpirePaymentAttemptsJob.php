<?php

namespace App\Jobs;

use App\Services\Payments\ExpirePaymentAttemptsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class ExpirePaymentAttemptsJob implements ShouldQueue
{
    use Queueable;

    public function handle(ExpirePaymentAttemptsService $service): void
    {
        $service->handle();
    }
}
