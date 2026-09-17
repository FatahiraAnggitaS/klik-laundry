<?php

namespace App\Services\Identity;

use App\Contracts\IdentityUser;
use App\Contracts\TransactionManagerInterface;
use App\DTOs\Audit\ActivityLogData;
use App\Repositories\Contracts\ActivityLogRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Contracts\Hashing\Hasher;

final readonly class ChangePasswordService
{
    public function __construct(
        private UserRepositoryInterface $users,
        private ActivityLogRepositoryInterface $activityLogs,
        private TransactionManagerInterface $transactions,
        private Hasher $hasher,
    ) {}

    public function handle(IdentityUser $user, string $password, bool $isReset): void
    {
        $this->transactions->run(function () use ($user, $password, $isReset): void {
            $this->users->updatePasswordHash($user, $this->hasher->make($password));
            $this->activityLogs->record(new ActivityLogData(
                tenantId: $user->tenantId(),
                actorId: $user->databaseId(),
                action: $isReset ? 'identity.password_reset' : 'identity.password_updated',
                subjectType: 'user',
                subjectId: $user->publicId(),
                after: ['sessionsRevoked' => true],
            ));
        });
    }
}
