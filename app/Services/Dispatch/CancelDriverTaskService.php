<?php

namespace App\Services\Dispatch;

use App\Contracts\IdentityUser;
use App\Contracts\TransactionManagerInterface;
use App\DTOs\Audit\ActivityLogData;
use App\Enums\DriverTaskStatus;
use App\Enums\DriverTaskType;
use App\Enums\PaymentStatus;
use App\Exceptions\Domain\DomainActionConflict;
use App\Exceptions\Domain\DomainRecordNotFound;
use App\Repositories\Contracts\ActivityLogRepositoryInterface;
use App\Repositories\Contracts\DispatchRepositoryInterface;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Services\Tenancy\TenantOperationsGuard;

final readonly class CancelDriverTaskService
{
    public function __construct(
        private DispatchRepositoryInterface $dispatch,
        private OrderRepositoryInterface $orders,
        private TenantOperationsGuard $guard,
        private ActivityLogRepositoryInterface $activityLogs,
        private TransactionManagerInterface $transactions,
    ) {}

    public function handle(IdentityUser $actor, string $taskPublicId, string $reason): void
    {
        $tenant = $this->guard->forRead($actor);

        $this->transactions->run(function () use ($actor, $tenant, $taskPublicId, $reason): void {
            $task = $this->dispatch->findTaskForTenant($tenant->id, $taskPublicId, true) ?? throw new DomainRecordNotFound;
            if ($task->type !== DriverTaskType::Pickup->value
                || in_array($task->status, [DriverTaskStatus::Completed->value, DriverTaskStatus::Cancelled->value], true)
                || $task->paymentStatus === PaymentStatus::Paid->value) {
                throw new DomainActionConflict('Task cannot be cancelled.', 'Task hanya dapat dibatalkan sebelum pickup selesai dan sebelum pembayaran berhasil.');
            }

            $this->dispatch->cancelForOrder($task->orderId, $actor->databaseId(), $reason);
            $this->orders->cancel($task->orderId, $actor->databaseId(), $reason);
            $this->activityLogs->record(new ActivityLogData(
                tenantId: $tenant->id,
                actorId: $actor->databaseId(),
                action: 'driver_task.cancelled_with_order',
                subjectType: 'delivery_task',
                subjectId: $task->publicId,
                reason: $reason,
                before: ['status' => $task->status],
                after: ['status' => DriverTaskStatus::Cancelled->value],
            ));
        });
    }
}
