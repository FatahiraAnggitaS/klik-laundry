<?php

namespace App\Jobs;

use App\Services\Privacy\CleanupExpiredProofsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class CleanupExpiredProofsJob implements ShouldQueue
{
    use Queueable;

    public function handle(CleanupExpiredProofsService $service): void
    {
        $service->handle();
    }
}
