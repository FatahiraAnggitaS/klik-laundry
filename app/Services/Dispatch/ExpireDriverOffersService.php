<?php

namespace App\Services\Dispatch;

use App\Contracts\TransactionManagerInterface;
use App\Events\DispatchLifecycleEvent;
use App\Repositories\Contracts\DispatchRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;

final readonly class ExpireDriverOffersService
{
    public function __construct(private DispatchRepositoryInterface $dispatch, private TransactionManagerInterface $transactions, private Dispatcher $events) {}

    public function handle(?CarbonImmutable $now = null): void
    {
        $now ??= CarbonImmutable::now();
        foreach ($this->dispatch->expiredOffers($now->toIso8601String()) as $offer) {
            $this->transactions->run(function () use ($offer): void {
                if ($this->dispatch->expireOffer($offer->id, $offer->taskId)) {
                    $this->events->dispatch(new DispatchLifecycleEvent('driver_task.offer_expired', $offer->task->publicId, $offer->task->orderPublicId, $offer->task->tenantId, $offer->driverId));
                }
            });
        }
    }
}
