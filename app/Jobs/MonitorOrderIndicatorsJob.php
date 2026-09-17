<?php

namespace App\Jobs;

use App\Services\Orders\MonitorOrderIndicatorsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class MonitorOrderIndicatorsJob implements ShouldQueue
{
    use Queueable;

    public function handle(MonitorOrderIndicatorsService $service): void
    {
        $service->handle();
    }
}
