<?php

namespace App\Jobs;

use App\Services\Privacy\ExpirePiiGrantsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class ExpirePiiAccessGrantsJob implements ShouldQueue
{
    use Queueable;

    public function handle(ExpirePiiGrantsService $service): void
    {
        $service->handle();
    }
}
