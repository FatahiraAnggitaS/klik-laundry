<?php

namespace App\Services\Dispatch;

use App\Contracts\IdentityUser;
use App\Contracts\TransactionManagerInterface;
use App\DTOs\Dispatch\DriverOfferData;
use App\Enums\DriverAvailability;
use App\Enums\DriverTaskStatus;
use App\Enums\UserStatus;
use App\Events\DispatchLifecycleEvent;
use App\Exceptions\Domain\DomainActionConflict;
use App\Exceptions\Domain\DomainRecordNotFound;
use App\Repositories\Contracts\DispatchRepositoryInterface;
use App\Repositories\Contracts\DriverRepositoryInterface;
use App\Services\Tenancy\TenantOperationsGuard;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;

final readonly class ReassignDriverTaskService
{
    public function __construct(private DispatchRepositoryInterface $dispatch, private DriverRepositoryInterface $drivers, private TenantOperationsGuard $guard, private TransactionManagerInterface $transactions, private Dispatcher $events) {}

    public function handle(IdentityUser $actor, string $taskPublicId, string $driverPublicId, string $reason): DriverOfferData
    {
        $tenant = $this->guard->forRead($actor);

        return $this->transactions->run(function () use ($actor, $tenant, $taskPublicId, $driverPublicId, $reason): DriverOfferData {
            $task = $this->dispatch->findTaskForTenant($tenant->id, $taskPublicId, true) ?? throw new DomainRecordNotFound;
            if (in_array($task->status, [DriverTaskStatus::Completed->value, DriverTaskStatus::Cancelled->value], true)) {
                throw new DomainActionConflict('Task is terminal.', 'Task terminal tidak dapat di-reassign.');
            }
            $driver = $this->drivers->findOwnedDriver($tenant->id, $driverPublicId, true) ?? throw new DomainRecordNotFound;
            if ($driver->status !== UserStatus::Active->value || $driver->availability !== DriverAvailability::Available->value || $this->dispatch->hasActiveTask($driver->id, $task->id)) {
                throw new DomainActionConflict('Replacement Driver is not eligible.', 'Driver pengganti tidak eligible.');
            }
            $reset = $this->dispatch->resetForReassignment($task->id, $actor->databaseId(), $reason);
            $offer = $this->dispatch->createOffer($reset->id, $driver->id, CarbonImmutable::now()->addMinutes(10)->toIso8601String(), $actor->databaseId());
            $this->events->dispatch(new DispatchLifecycleEvent('driver_task.reassigned', $task->publicId, $task->orderPublicId, $tenant->id, $driver->id));

            return $offer;
        });
    }
}
