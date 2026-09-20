<?php

namespace App\Services\Dispatch;

use App\Contracts\IdentityUser;
use App\Contracts\PrivateProofStorageInterface;
use App\Contracts\TransactionManagerInterface;
use App\DTOs\Dispatch\DriverTaskData;
use App\Enums\DriverTaskStatus;
use App\Enums\DriverTaskType;
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

            return $this->dispatch->startTask($task->id, $actor->databaseId());
        });
    }

    public function complete(IdentityUser $actor, string $taskPublicId, ?string $note, ?UploadedFile $proof): DriverTaskData
    {
        $this->assertDriver($actor);
        $stored = $proof === null ? null : $this->proofs->store('dispatch/task-proofs', $proof);
        try {
            return $this->transactions->run(function () use ($actor, $taskPublicId, $note, $stored): DriverTaskData {
                $task = $this->dispatch->findTaskForDriver($actor->databaseId(), $taskPublicId, true) ?? throw new DomainRecordNotFound;
                if ($task->status === DriverTaskStatus::Completed->value) {
                    return $task;
                }
                if ($task->type !== DriverTaskType::Pickup->value) {
                    throw new DomainActionConflict('Delivery completion belongs to Milestone 7.', 'Penyelesaian delivery tersedia setelah flow completion M7 aktif.');
                }
                if ($task->status !== DriverTaskStatus::InProgress->value) {
                    throw new DomainActionConflict('Task is not in progress.', 'Task harus dimulai sebelum diselesaikan.');
                }
                if ($task->pricingType === 'fixed' && $task->paymentStatus !== PaymentStatus::Paid->value) {
                    throw new DomainActionConflict('Fixed order has not been paid.', 'Order fixed belum memiliki pembayaran terverifikasi.');
                }
                $completed = $this->dispatch->completePickupTask($task->id, $actor->databaseId(), $note, $stored);
                $this->events->dispatch(new DispatchLifecycleEvent('driver_task.completed', $completed->publicId, $completed->orderPublicId, $completed->tenantId, $actor->databaseId()));

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
