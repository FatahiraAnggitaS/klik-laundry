<?php

namespace App\Services\Identity;

use App\Contracts\IdentityUser;
use App\Contracts\TransactionManagerInterface;
use App\DTOs\Audit\ActivityLogData;
use App\Repositories\Contracts\ActivityLogRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;

final readonly class UpdateProfileService
{
    public function __construct(
        private UserRepositoryInterface $users,
        private ActivityLogRepositoryInterface $activityLogs,
        private TransactionManagerInterface $transactions,
    ) {}

    public function handle(IdentityUser $actor, string $name, string $phone): IdentityUser
    {
        return $this->transactions->run(function () use ($actor, $name, $phone): IdentityUser {
            $updated = $this->users->updateProfile($actor, $name, $phone);

            $this->activityLogs->record(new ActivityLogData(
                tenantId: $actor->tenantId(),
                actorId: $actor->databaseId(),
                action: 'identity.profile_updated',
                subjectType: 'user',
                subjectId: $actor->publicId(),
                after: [
                    'nameChanged' => $actor->displayName() !== $updated->displayName(),
                    'phoneChanged' => $actor->phoneNumber() !== $updated->phoneNumber(),
                ],
            ));

            return $updated;
        });
    }
}
