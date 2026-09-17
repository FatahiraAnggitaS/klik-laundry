<?php

namespace App\Services\Tenancy;

use App\Contracts\IdentityUser;
use App\Contracts\TransactionManagerInterface;
use App\DTOs\Audit\ActivityLogData;
use App\DTOs\Tenancy\TenantResubmissionData;
use App\Enums\TenantOnboardingStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Exceptions\Domain\DomainActionConflict;
use App\Exceptions\Domain\DomainRecordNotFound;
use App\Repositories\Contracts\ActivityLogRepositoryInterface;
use App\Repositories\Contracts\TenantRepositoryInterface;

final readonly class ResubmitTenantApplicationService
{
    public function __construct(
        private TenantRepositoryInterface $tenants,
        private ActivityLogRepositoryInterface $activityLogs,
        private TransactionManagerInterface $transactions,
    ) {}

    public function handle(IdentityUser $actor, TenantResubmissionData $data): void
    {
        $tenantId = $actor->tenantId();

        if ($actor->role() !== UserRole::TenantOwner || $actor->status() !== UserStatus::Active || $tenantId === null) {
            throw new DomainRecordNotFound;
        }

        $this->transactions->run(function () use ($actor, $tenantId, $data): void {
            $tenant = $this->tenants->lockOwnedByTenantId($tenantId) ?? throw new DomainRecordNotFound;

            if ($tenant->onboardingStatus !== TenantOnboardingStatus::Rejected->value) {
                throw new DomainActionConflict('Only rejected tenant applications can be resubmitted.', 'Hanya pendaftaran ditolak yang dapat diajukan ulang.');
            }

            $updated = $this->tenants->resubmit($tenant->id, $data);
            $this->activityLogs->record(new ActivityLogData(
                tenantId: $tenant->id,
                actorId: $actor->databaseId(),
                action: 'tenant.resubmitted',
                subjectType: 'tenant',
                subjectId: $tenant->publicId,
                before: ['onboardingStatus' => $tenant->onboardingStatus],
                after: ['onboardingStatus' => $updated->onboardingStatus],
            ));
        });
    }
}
