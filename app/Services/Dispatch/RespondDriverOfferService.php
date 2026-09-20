<?php

namespace App\Services\Dispatch;

use App\Contracts\IdentityUser;
use App\Contracts\TransactionManagerInterface;
use App\DTOs\Dispatch\DriverTaskData;
use App\Enums\DriverAvailability;
use App\Enums\DriverOfferStatus;
use App\Enums\DriverTaskStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Events\DispatchLifecycleEvent;
use App\Exceptions\Domain\DomainActionConflict;
use App\Exceptions\Domain\DomainRecordNotFound;
use App\Repositories\Contracts\DispatchRepositoryInterface;
use App\Repositories\Contracts\DriverRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;

final readonly class RespondDriverOfferService
{
    public function __construct(
        private DispatchRepositoryInterface $dispatch,
        private DriverRepositoryInterface $drivers,
        private TransactionManagerInterface $transactions,
        private Dispatcher $events,
    ) {}

    public function handle(IdentityUser $actor, string $offerPublicId, bool $accept, ?CarbonImmutable $now = null): DriverTaskData
    {
        $this->assertDriver($actor);
        $now ??= CarbonImmutable::now();

        return $this->transactions->run(function () use ($actor, $offerPublicId, $accept, $now): DriverTaskData {
            $driver = $this->drivers->findDriver($actor->databaseId(), true) ?? throw new DomainRecordNotFound;
            $offer = $this->dispatch->findOfferForDriver($driver->id, $offerPublicId, true) ?? throw new DomainRecordNotFound;
            if ($offer->status !== DriverOfferStatus::Offered->value || $offer->task->status !== DriverTaskStatus::Offered->value || CarbonImmutable::parse($offer->expiresAt)->lte($now)) {
                throw new DomainActionConflict('Offer is no longer active.', 'Offer sudah tidak aktif.');
            }
            if (! $accept) {
                $task = $this->dispatch->rejectOffer($offer->id, $offer->taskId, $driver->id);
                $this->events->dispatch(new DispatchLifecycleEvent('driver_task.rejected', $task->publicId, $task->orderPublicId, $task->tenantId, $driver->id));

                return $task;
            }
            if ($driver->status !== UserStatus::Active->value || $driver->availability !== DriverAvailability::Available->value || $this->dispatch->hasActiveTask($driver->id, $offer->taskId)) {
                throw new DomainActionConflict('Driver is no longer eligible.', 'Driver tidak lagi eligible menerima offer.');
            }
            $task = $this->dispatch->acceptOffer($offer->id, $offer->taskId, $driver->id);
            $this->events->dispatch(new DispatchLifecycleEvent('driver_task.accepted', $task->publicId, $task->orderPublicId, $task->tenantId, $driver->id));

            return $task;
        });
    }

    private function assertDriver(IdentityUser $actor): void
    {
        if ($actor->role() !== UserRole::Driver || $actor->status() !== UserStatus::Active || $actor->tenantId() === null) {
            throw new DomainRecordNotFound;
        }
    }
}
