<?php

namespace App\Services\Tenancy;

use App\Contracts\IdentityUser;
use App\Contracts\TransactionManagerInterface;
use App\DTOs\Audit\ActivityLogData;
use App\Enums\TenantOnboardingStatus;
use App\Enums\TenantOperationalStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Exceptions\Domain\DomainActionConflict;
use App\Exceptions\Domain\DomainRecordNotFound;
use App\Repositories\Contracts\ActivityLogRepositoryInterface;
use App\Repositories\Contracts\TenantRepositoryInterface;

final readonly class RequestTenantClosureService
{
    public function __construct(
        private TenantRepositoryInterface $tenants,
        private ActivityLogRepositoryInterface $activityLogs,
        private TransactionManagerInterface $transactions,
    ) {}

    public function handle(IdentityUser $actor, string $reason): void
    {
        $tenantId = $actor->tenantId();

        if ($actor->role() !== UserRole::TenantOwner || $actor->status() !== UserStatus::Active || $tenantId === null) {
            throw new DomainRecordNotFound;
        }

        $this->transactions->run(function () use ($actor, $tenantId, $reason): void {
            $tenant = $this->tenants->lockOwnedByTenantId($tenantId) ?? throw new DomainRecordNotFound;

            if ($tenant->onboardingStatus !== TenantOnboardingStatus::Approved->value
                || ! in_array($tenant->operationalStatus, [TenantOperationalStatus::Active->value, TenantOperationalStatus::Suspended->value], true)
                || $tenant->closureRequested) {
                throw new DomainActionConflict('Tenant closure request is not allowed.', 'Permintaan penutupan Tenant tidak diizinkan.');
            }

            $this->tenants->requestClosure($tenant->id);
            $this->activityLogs->record(new ActivityLogData(
                tenantId: $tenant->id,
                actorId: $actor->databaseId(),
                action: 'tenant.closure_requested',
                subjectType: 'tenant',
                subjectId: $tenant->publicId,
                reason: $reason,
                after: ['closureRequested' => true],
            ));
        });
    }
}
