<?php

namespace App\Services\Identity;

use App\Contracts\IdentityUser;
use App\Contracts\TransactionManagerInterface;
use App\DTOs\Audit\ActivityLogData;
use App\DTOs\Identity\IdentityRegistrationData;
use App\Repositories\Contracts\ActivityLogRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Contracts\Hashing\Hasher;

final readonly class RegisterCustomerService
{
    public function __construct(
        private UserRepositoryInterface $users,
        private ActivityLogRepositoryInterface $activityLogs,
        private TransactionManagerInterface $transactions,
        private Hasher $hasher,
    ) {}

    public function handle(string $name, string $email, string $phone, string $password): IdentityUser
    {
        return $this->transactions->run(function () use ($name, $email, $phone, $password): IdentityUser {
            $user = $this->users->createCustomer(new IdentityRegistrationData(
                name: $name,
                email: $email,
                phone: $phone,
                passwordHash: $this->hasher->make($password),
            ));

            $this->activityLogs->record(new ActivityLogData(
                tenantId: null,
                actorId: $user->databaseId(),
                action: 'identity.customer_registered',
                subjectType: 'user',
                subjectId: $user->publicId(),
                after: ['role' => $user->role()->value, 'emailVerified' => false],
            ));

            return $user;
        });
    }
}
