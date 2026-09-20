<?php

namespace App\Services\Dispatch;

use App\Contracts\IdentityUser;
use App\Contracts\TransactionManagerInterface;
use App\DTOs\Audit\ActivityLogData;
use App\DTOs\Dispatch\DriverOfferData;
use App\Enums\DriverAvailability;
use App\Enums\DriverTaskStatus;
use App\Enums\DriverTaskType;
use App\Enums\FulfillmentStatus;
use App\Enums\UserStatus;
use App\Events\DispatchLifecycleEvent;
use App\Exceptions\Domain\DomainActionConflict;
use App\Exceptions\Domain\DomainRecordNotFound;
use App\Repositories\Contracts\ActivityLogRepositoryInterface;
use App\Repositories\Contracts\DispatchRepositoryInterface;
use App\Repositories\Contracts\DriverRepositoryInterface;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Services\Tenancy\TenantOperationsGuard;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;

final readonly class OfferDriverTaskService
{
    public function __construct(
        private OrderRepositoryInterface $orders,
        private DriverRepositoryInterface $drivers,
        private DispatchRepositoryInterface $dispatch,
        private TenantOperationsGuard $guard,
        private ActivityLogRepositoryInterface $activityLogs,
        private TransactionManagerInterface $transactions,
        private Dispatcher $events,
    ) {}

    public function handle(IdentityUser $actor, string $orderPublicId, string $driverPublicId, DriverTaskType $type = DriverTaskType::Pickup, ?CarbonImmutable $now = null): DriverOfferData
    {
        $tenant = $this->guard->forRead($actor);
        $now ??= CarbonImmutable::now();

        return $this->transactions->run(function () use ($actor, $tenant, $orderPublicId, $driverPublicId, $type, $now): DriverOfferData {
            $order = $this->orders->lockByPublicId($orderPublicId) ?? throw new DomainRecordNotFound;
            if ($order->tenantId !== $tenant->id) {
                throw new DomainRecordNotFound;
            }
            $expected = $type === DriverTaskType::Pickup ? FulfillmentStatus::AwaitingPickup->value : FulfillmentStatus::ReadyForDelivery->value;
            if ($order->fulfillmentStatus !== $expected || ($type === DriverTaskType::Delivery && $order->deliveryStartsAt === null)) {
                throw new DomainActionConflict('Order is not eligible for this task.', 'Order belum siap ditugaskan untuk leg ini.');
            }
            $driver = $this->drivers->findOwnedDriver($tenant->id, $driverPublicId, true) ?? throw new DomainRecordNotFound;
            if ($driver->status !== UserStatus::Active->value || $driver->availability !== DriverAvailability::Available->value || $this->dispatch->hasActiveTask($driver->id)) {
                throw new DomainActionConflict('Driver is not eligible for a new offer.', 'Driver tidak aktif, tidak available, atau masih memiliki task aktif.');
            }
            $settings = $this->drivers->settings($tenant->id);
            $commission = $type === DriverTaskType::Pickup ? $settings->pickupCommission : $settings->deliveryCommission;
            if ($commission === null) {
                throw new DomainActionConflict('Commission is not configured.', 'Tarif komisi leg ini belum dikonfigurasi.');
            }
            $task = $this->dispatch->createTask($order, $type, $commission);
            if ($task->status !== DriverTaskStatus::Pending->value) {
                throw new DomainActionConflict('Task already has an active lifecycle.', 'Task sudah memiliki offer atau assignment aktif.');
            }
            $offer = $this->dispatch->createOffer($task->id, $driver->id, $now->addMinutes(10)->toIso8601String(), $actor->databaseId());
            $this->activityLogs->record(new ActivityLogData($tenant->id, $actor->databaseId(), 'driver_task.offered', 'delivery_task', $task->publicId, after: ['type' => $type->value, 'commissionAmount' => $commission]));
            $this->events->dispatch(new DispatchLifecycleEvent('driver_task.offered', $task->publicId, $order->publicId, $tenant->id, $driver->id));

            return $offer;
        });
    }
}
