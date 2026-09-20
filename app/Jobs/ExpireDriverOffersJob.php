<?php

namespace App\Jobs;

use App\Services\Dispatch\ExpireDriverOffersService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class ExpireDriverOffersJob implements ShouldQueue
{
    use Queueable;

    public function handle(ExpireDriverOffersService $service): void
    {
        $service->handle();
    }
}
