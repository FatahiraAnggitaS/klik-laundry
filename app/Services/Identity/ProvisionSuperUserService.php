<?php

namespace App\Services\Identity;

use App\Contracts\IdentityUser;
use App\Contracts\TransactionManagerInterface;
use App\DTOs\Audit\ActivityLogData;
use App\DTOs\Identity\IdentityRegistrationData;
use App\Exceptions\Domain\DomainActionConflict;
use App\Repositories\Contracts\ActivityLogRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Contracts\Hashing\Hasher;

final readonly class ProvisionSuperUserService
{
    public function __construct(
        private UserRepositoryInterface $users,
        private ActivityLogRepositoryInterface $activityLogs,
        private TransactionManagerInterface $transactions,
        private Hasher $hasher,
    ) {}

    public function exists(): bool
    {
        return $this->users->superUserExists();
    }

    public function handle(string $name, string $email, string $phone, string $password): IdentityUser
    {
        return $this->transactions->run(function () use ($name, $email, $phone, $password): IdentityUser {
            if ($this->users->superUserExists()) {
                throw new DomainActionConflict(
                    'A Super User account already exists.',
                    'Super User sudah tersedia. Provisioning kedua ditolak.',
                );
            }

            $user = $this->users->createSuperUser(new IdentityRegistrationData(
                name: $name,
                email: $email,
                phone: $phone,
                passwordHash: $this->hasher->make($password),
            ));

            $this->activityLogs->record(new ActivityLogData(
                tenantId: null,
                actorId: $user->databaseId(),
                action: 'super_user.provisioned',
                subjectType: 'user',
                subjectId: $user->publicId(),
                after: ['role' => $user->role()->value, 'emailVerified' => false],
            ));

            return $user;
        });
    }
}
