<?php

namespace App\Services\Tenancy;

use App\Contracts\IdentityUser;
use App\Contracts\TransactionManagerInterface;
use App\DTOs\Audit\ActivityLogData;
use App\Enums\TenantOperationalStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Exceptions\Domain\DomainActionConflict;
use App\Exceptions\Domain\DomainRecordNotFound;
use App\Repositories\Contracts\ActivityLogRepositoryInterface;
use App\Repositories\Contracts\TenantRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;

final readonly class CloseTenantService
{
    public function __construct(
        private TenantRepositoryInterface $tenants,
        private UserRepositoryInterface $users,
        private ActivityLogRepositoryInterface $activityLogs,
        private TransactionManagerInterface $transactions,
    ) {}

    public function handle(IdentityUser $actor, string $tenantPublicId, string $reason): void
    {
        if ($actor->role() !== UserRole::SuperUser || $actor->status() !== UserStatus::Active) {
            throw new DomainRecordNotFound;
        }

        $this->transactions->run(function () use ($actor, $tenantPublicId, $reason): void {
            $tenant = $this->tenants->lockByPublicIdForReview($tenantPublicId) ?? throw new DomainRecordNotFound;

            if (! $tenant->closureRequested || $tenant->operationalStatus === TenantOperationalStatus::Closed->value) {
                throw new DomainActionConflict('Tenant is not ready for closure.', 'Tenant belum dapat ditutup.');
            }

            $this->tenants->close($tenant->id, $actor->databaseId(), $reason);
            $this->users->closeTenantUsersAndRevokeSessions($tenant->id, $reason);
            $this->activityLogs->record(new ActivityLogData(
                tenantId: $tenant->id,
                actorId: $actor->databaseId(),
                action: 'tenant.closed',
                subjectType: 'tenant',
                subjectId: $tenant->publicId,
                reason: $reason,
                before: ['operationalStatus' => $tenant->operationalStatus],
                after: ['operationalStatus' => TenantOperationalStatus::Closed->value],
            ));
        });
    }
}
