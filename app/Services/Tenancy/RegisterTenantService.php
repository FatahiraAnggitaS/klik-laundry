<?php

namespace App\Services\Tenancy;

use App\Contracts\IdentityUser;
use App\Contracts\TransactionManagerInterface;
use App\DTOs\Audit\ActivityLogData;
use App\DTOs\Identity\IdentityRegistrationData;
use App\DTOs\Tenancy\TenantRegistrationData;
use App\Repositories\Contracts\ActivityLogRepositoryInterface;
use App\Repositories\Contracts\TenantRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Hashing\Hasher;

final readonly class RegisterTenantService
{
    public function __construct(
        private TenantRepositoryInterface $tenants,
        private UserRepositoryInterface $users,
        private ActivityLogRepositoryInterface $activityLogs,
        private TransactionManagerInterface $transactions,
        private Dispatcher $events,
        private Hasher $hasher,
    ) {}

    public function handle(TenantRegistrationData $data): IdentityUser
    {
        $owner = $this->transactions->run(function () use ($data): IdentityUser {
            $tenant = $this->tenants->create($data);
            $owner = $this->users->createTenantOwner($tenant->id, new IdentityRegistrationData(
                name: $data->ownerName,
                email: $data->email,
                phone: $data->phone,
                passwordHash: $this->hasher->make($data->password),
            ));

            $this->activityLogs->record(new ActivityLogData(
                tenantId: $tenant->id,
                actorId: $owner->databaseId(),
                action: 'tenant.registered',
                subjectType: 'tenant',
                subjectId: $tenant->publicId,
                after: ['onboardingStatus' => $tenant->onboardingStatus, 'operationalStatus' => $tenant->operationalStatus],
            ));

            return $owner;
        });

        $this->events->dispatch(new Registered($owner));

        return $owner;
    }
}
