<?php

namespace App\Services\Identity;

use App\Contracts\IdentityUser;
use App\Contracts\TransactionManagerInterface;
use App\DTOs\Audit\ActivityLogData;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Exceptions\Domain\DomainActionConflict;
use App\Exceptions\Domain\DomainRecordNotFound;
use App\Repositories\Contracts\ActivityLogRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;

final readonly class ChangeUserStatusService
{
    public function __construct(
        private UserRepositoryInterface $users,
        private ActivityLogRepositoryInterface $activityLogs,
        private TransactionManagerInterface $transactions,
    ) {}

    public function handle(IdentityUser $actor, string $targetPublicId, UserStatus $status, string $reason): void
    {
        if ($actor->role() !== UserRole::SuperUser || $actor->status() !== UserStatus::Active) {
            throw new DomainRecordNotFound;
        }

        if (! in_array($status, [UserStatus::Active, UserStatus::Suspended], true)) {
            throw new DomainActionConflict(
                'Only explicit suspend and reactivate transitions are supported.',
                'Perubahan status akun tidak diizinkan.',
            );
        }

        $this->transactions->run(function () use ($actor, $targetPublicId, $status, $reason): void {
            $target = $this->users->lockByPublicId($targetPublicId) ?? throw new DomainRecordNotFound;

            if ($target->role() === UserRole::SuperUser || $target->databaseId() === $actor->databaseId()) {
                throw new DomainActionConflict(
                    'Super User accounts cannot be changed through generic account lifecycle actions.',
                    'Akun Super User tidak dapat diubah melalui tindakan ini.',
                );
            }

            $expected = $status === UserStatus::Suspended ? UserStatus::Active : UserStatus::Suspended;

            if ($target->status() !== $expected) {
                throw new DomainActionConflict(
                    'The requested user status transition is invalid.',
                    'Perubahan status akun tidak diizinkan.',
                );
            }

            $this->users->changeStatusAndRevokeSessions($target, $status, $reason);
            $this->activityLogs->record(new ActivityLogData(
                tenantId: $target->tenantId(),
                actorId: $actor->databaseId(),
                action: $status === UserStatus::Suspended ? 'user.suspended' : 'user.reactivated',
                subjectType: 'user',
                subjectId: $target->publicId(),
                reason: $reason,
                before: ['status' => $target->status()->value],
                after: ['status' => $status->value],
            ));
        });
    }
}
