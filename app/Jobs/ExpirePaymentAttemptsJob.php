<?php

namespace App\Jobs;

use App\Repositories\Contracts\PaymentRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class ExpirePaymentAttemptsJob implements ShouldQueue
{
    use Queueable;

    public function handle(PaymentRepositoryInterface $payments): void
    {
        $payments->expireOverdue(CarbonImmutable::now('Asia/Jakarta'));
    }
}
