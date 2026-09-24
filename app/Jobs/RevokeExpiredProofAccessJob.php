<?php

namespace App\Jobs;

use App\Services\Dispatch\RevokeExpiredProofAccessService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class RevokeExpiredProofAccessJob implements ShouldQueue
{
    use Queueable;

    public function handle(RevokeExpiredProofAccessService $service): void
    {
        $service->handle();
    }
}
