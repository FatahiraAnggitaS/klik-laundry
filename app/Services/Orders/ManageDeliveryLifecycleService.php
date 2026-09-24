<?php

namespace App\Services\Orders;

use App\Contracts\IdentityUser;
use App\Contracts\TransactionManagerInterface;
use App\DTOs\Audit\ActivityLogData;
use App\DTOs\Orders\OrderData;
use App\Enums\DriverTaskStatus;
use App\Enums\DriverTaskType;
use App\Enums\FulfillmentStatus;
use App\Enums\OrderIndicatorType;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Events\DispatchLifecycleEvent;
use App\Exceptions\Domain\DomainActionConflict;
use App\Exceptions\Domain\DomainRecordNotFound;
use App\Repositories\Contracts\ActivityLogRepositoryInterface;
use App\Repositories\Contracts\DispatchRepositoryInterface;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Repositories\Contracts\OutletRepositoryInterface;
use App\Services\Tenancy\TenantOperationsGuard;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;

final readonly class ManageDeliveryLifecycleService
{
    public function __construct(
        private OrderRepositoryInterface $orders,
        private OutletRepositoryInterface $outlets,
        private DispatchRepositoryInterface $dispatch,
        private ValidateDeliveryScheduleService $schedule,
        private TenantOperationsGuard $guard,
        private ActivityLogRepositoryInterface $activityLogs,
        private TransactionManagerInterface $transactions,
        private Dispatcher $events,
    ) {}

    public function markReady(IdentityUser $actor, string $orderPublicId): OrderData
    {
        $tenant = $this->guard->forExistingWork($actor);

        return $this->transactions->run(function () use ($actor, $tenant, $orderPublicId): OrderData {
            $order = $this->orders->lockByPublicId($orderPublicId) ?? throw new DomainRecordNotFound;
            if ($order->tenantId !== $tenant->id) {
                throw new DomainRecordNotFound;
            }
            if ($order->fulfillmentStatus !== FulfillmentStatus::Processing->value || $order->paymentStatus !== PaymentStatus::Paid->value) {
                throw new DomainActionConflict('Order is not ready.', 'Hanya order processing dengan pembayaran terverifikasi yang dapat dinyatakan siap.');
            }

            $ready = $this->orders->markReadyForDelivery($order->id, $actor->databaseId());
            $this->orders->syncIndicator($order->id, OrderIndicatorType::Delayed, false, now()->utc()->toIso8601String());
            $this->activityLogs->record(new ActivityLogData($tenant->id, $actor->databaseId(), 'order.ready_for_delivery', 'order', $order->publicId, after: ['fulfillmentStatus' => $ready->fulfillmentStatus]));
            $this->events->dispatch(new DispatchLifecycleEvent('order.ready_for_delivery', null, $order->publicId, $tenant->id, customerId: $order->customerId));

            return $ready;
        });
    }

    public function schedule(IdentityUser $actor, string $orderPublicId, string $slotPublicId, string $date, ?string $reason, ?CarbonImmutable $now = null): OrderData
    {
        $now ??= CarbonImmutable::now('Asia/Jakarta');

        return $this->transactions->run(function () use ($actor, $orderPublicId, $slotPublicId, $date, $reason, $now): OrderData {
            $order = $this->resolveOwnedOrder($actor, $orderPublicId);
            if ($order->fulfillmentStatus !== FulfillmentStatus::ReadyForDelivery->value || $order->readyAt === null) {
                throw new DomainActionConflict('Order is not awaiting a delivery schedule.', 'Order belum siap memilih jadwal delivery.');
            }

            $readyDeadline = CarbonImmutable::parse($order->readyAt, 'Asia/Jakarta')->addDays(7);
            $isTenant = $actor->role() === UserRole::TenantOwner;
            if ($isTenant && ($reason === null || mb_strlen(trim($reason)) < 5)) {
                throw new DomainActionConflict('A reason is required.', 'Alasan minimal 5 karakter wajib diisi oleh Tenant.');
            }
            if (! $isTenant && $now->greaterThan($readyDeadline)) {
                throw new DomainActionConflict('Customer scheduling window has ended.', 'Batas tujuh hari pemilihan jadwal telah berakhir. Hubungi Tenant.');
            }
            if ($isTenant && $order->deliveryStartsAt === null && $now->lessThanOrEqualTo($readyDeadline)) {
                throw new DomainActionConflict('Customer scheduling window is still active.', 'Tenant baru dapat memilih jadwal awal setelah batas Customer berakhir.');
            }

            $task = $this->dispatch->findTaskForOrder($order->id, DriverTaskType::Delivery, true);
            if ($task !== null) {
                if ($task->status === DriverTaskStatus::InProgress->value || $task->status === DriverTaskStatus::Completed->value) {
                    throw new DomainActionConflict('Delivery is already in progress.', 'Jadwal tidak dapat diubah setelah delivery dimulai.');
                }
                if (! $isTenant && $task->status === DriverTaskStatus::Accepted->value) {
                    throw new DomainActionConflict('Driver has accepted the task.', 'Jadwal tidak dapat diubah setelah Driver menerima task.');
                }
                if (in_array($task->status, [DriverTaskStatus::Offered->value, DriverTaskStatus::Accepted->value], true)) {
                    $this->dispatch->resetForReassignment($task->id, $actor->databaseId(), $reason ?? 'Jadwal delivery diubah Customer.');
                }
            }

            $outlet = $this->outlets->findOwned($order->tenantId, $order->outletPublicId) ?? throw new DomainRecordNotFound;
            $latest = $isTenant ? $now->addDays(7) : $readyDeadline;
            $validated = $this->schedule->handle($outlet, $slotPublicId, $date, $latest, $now);
            $scheduled = $this->orders->scheduleDelivery($order->id, $validated['id'], $validated['startsAt'], $validated['endsAt'], $actor->databaseId(), $reason);
            $this->dispatch->updateDeliverySchedule($order->id, $validated['startsAt'], $validated['endsAt']);
            $this->orders->syncIndicator($order->id, OrderIndicatorType::AwaitingCustomer, false, $now->utc()->toIso8601String());
            $this->activityLogs->record(new ActivityLogData($order->tenantId, $actor->databaseId(), 'order.delivery_scheduled', 'order', $order->publicId, reason: $reason, after: ['scheduled' => true]));
            $this->events->dispatch(new DispatchLifecycleEvent('order.delivery_scheduled', $task?->publicId, $order->publicId, $order->tenantId, customerId: $order->customerId));

            return $scheduled;
        });
    }

    private function resolveOwnedOrder(IdentityUser $actor, string $publicId): OrderData
    {
        if ($actor->status() !== UserStatus::Active) {
            throw new DomainRecordNotFound;
        }
        if ($actor->role() === UserRole::Customer) {
            return $this->orders->lockCustomerOrder($actor->databaseId(), $publicId) ?? throw new DomainRecordNotFound;
        }
        if ($actor->role() === UserRole::TenantOwner) {
            $tenant = $this->guard->forExistingWork($actor);
            $order = $this->orders->lockByPublicId($publicId) ?? throw new DomainRecordNotFound;
            if ($order->tenantId !== $tenant->id) {
                throw new DomainRecordNotFound;
            }

            return $order;
        }

        throw new DomainRecordNotFound;
    }
}
