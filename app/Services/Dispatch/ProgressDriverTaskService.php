<?php

namespace App\Services\Dispatch;

use App\Contracts\IdentityUser;
use App\Contracts\PrivateProofStorageInterface;
use App\Contracts\TransactionManagerInterface;
use App\DTOs\Dispatch\DriverTaskData;
use App\Enums\DriverTaskStatus;
use App\Enums\DriverTaskType;
use App\Enums\FulfillmentStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Events\DispatchLifecycleEvent;
use App\Exceptions\Domain\DomainActionConflict;
use App\Exceptions\Domain\DomainRecordNotFound;
use App\Repositories\Contracts\DispatchRepositoryInterface;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\UploadedFile;

final readonly class ProgressDriverTaskService
{
    public function __construct(
        private DispatchRepositoryInterface $dispatch,
        private PrivateProofStorageInterface $proofs,
        private TransactionManagerInterface $transactions,
        private Dispatcher $events,
    ) {}

    public function start(IdentityUser $actor, string $taskPublicId): DriverTaskData
    {
        $this->assertDriver($actor);

        return $this->transactions->run(function () use ($actor, $taskPublicId): DriverTaskData {
            $task = $this->dispatch->findTaskForDriver($actor->databaseId(), $taskPublicId, true) ?? throw new DomainRecordNotFound;
            if ($task->status !== DriverTaskStatus::Accepted->value) {
                throw new DomainActionConflict('Task cannot be started.', 'Task tidak dapat dimulai dari status saat ini.');
            }

            $expectedOrderStatus = $task->type === DriverTaskType::Pickup->value
                ? FulfillmentStatus::PickupAssigned->value
                : FulfillmentStatus::DeliveryAssigned->value;
            if ($task->orderStatus !== $expectedOrderStatus) {
                throw new DomainActionConflict('Order is not ready for this task.', 'Status order tidak sesuai dengan task Driver.');
            }

            $started = $this->dispatch->startTask($task->id, $actor->databaseId());
            if ($task->type === DriverTaskType::Delivery->value) {
                $this->events->dispatch(new DispatchLifecycleEvent('order.out_for_delivery', $started->publicId, $started->orderPublicId, $started->tenantId, $actor->databaseId()));
            }

            return $started;
        });
    }

    public function complete(IdentityUser $actor, string $taskPublicId, ?string $note, ?UploadedFile $proof): DriverTaskData
    {
        $this->assertDriver($actor);
        $existing = $this->dispatch->findTaskForDriver($actor->databaseId(), $taskPublicId);
        if ($existing === null) {
            throw new DomainRecordNotFound;
        }
        if ($existing->status === DriverTaskStatus::Completed->value) {
            return $existing;
        }
        $stored = $proof === null ? null : $this->proofs->store('dispatch/task-proofs', $proof);
        try {
            return $this->transactions->run(function () use ($actor, $taskPublicId, $note, $stored): DriverTaskData {
                $task = $this->dispatch->findTaskForDriver($actor->databaseId(), $taskPublicId, true) ?? throw new DomainRecordNotFound;
                if ($task->status === DriverTaskStatus::Completed->value) {
                    if ($stored !== null) {
                        $this->proofs->delete($stored['disk'], $stored['key']);
                    }

                    return $task;
                }
                if ($task->status !== DriverTaskStatus::InProgress->value) {
                    throw new DomainActionConflict('Task is not in progress.', 'Task harus dimulai sebelum diselesaikan.');
                }
                if ($task->pricingType === 'fixed' && $task->paymentStatus !== PaymentStatus::Paid->value) {
                    throw new DomainActionConflict('Fixed order has not been paid.', 'Order fixed belum memiliki pembayaran terverifikasi.');
                }
                $expectedOrderStatus = $task->type === DriverTaskType::Pickup->value
                    ? FulfillmentStatus::PickupAssigned->value
                    : FulfillmentStatus::OutForDelivery->value;
                if ($task->orderStatus !== $expectedOrderStatus) {
                    throw new DomainActionConflict('Order is not ready for completion.', 'Status order tidak sesuai untuk penyelesaian task.');
                }
                $completed = $this->dispatch->completeTask($task->id, $actor->databaseId(), $note, $stored);
                $event = $task->type === DriverTaskType::Delivery->value ? 'order.completed' : 'driver_task.completed';
                $this->events->dispatch(new DispatchLifecycleEvent($event, $completed->publicId, $completed->orderPublicId, $completed->tenantId, $actor->databaseId()));
                if ($task->type === DriverTaskType::Pickup->value && $task->pricingType === 'fixed') {
                    $this->events->dispatch(new DispatchLifecycleEvent('order.processing', $completed->publicId, $completed->orderPublicId, $completed->tenantId));
                }

                return $completed;
            });
        } catch (\Throwable $exception) {
            if ($stored !== null) {
                $this->proofs->delete($stored['disk'], $stored['key']);
            }
            throw $exception;
        }
    }

    private function assertDriver(IdentityUser $actor): void
    {
        if ($actor->role() !== UserRole::Driver || $actor->status() !== UserStatus::Active) {
            throw new DomainRecordNotFound;
        }
    }
}
