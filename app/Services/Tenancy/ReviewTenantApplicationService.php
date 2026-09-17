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

final readonly class ReviewTenantApplicationService
{
    public function __construct(
        private TenantRepositoryInterface $tenants,
        private ActivityLogRepositoryInterface $activityLogs,
        private TransactionManagerInterface $transactions,
    ) {}

    public function handle(IdentityUser $actor, string $tenantPublicId, TenantOnboardingStatus $decision, string $reason): void
    {
        $this->assertSuperUser($actor);

        if (! in_array($decision, [TenantOnboardingStatus::Approved, TenantOnboardingStatus::Rejected], true)) {
            throw new DomainActionConflict('Invalid tenant review decision.', 'Keputusan review Tenant tidak valid.');
        }

        $this->transactions->run(function () use ($actor, $tenantPublicId, $decision, $reason): void {
            $tenant = $this->tenants->lockByPublicIdForReview($tenantPublicId) ?? throw new DomainRecordNotFound;

            if ($tenant->onboardingStatus !== TenantOnboardingStatus::Pending->value) {
                throw new DomainActionConflict('Only pending tenants can be reviewed.', 'Hanya pendaftaran pending yang dapat direview.');
            }

            $operational = $decision === TenantOnboardingStatus::Approved
                ? TenantOperationalStatus::Active
                : TenantOperationalStatus::Inactive;

            $this->tenants->setReviewDecision($tenant->id, $decision, $operational, $actor->databaseId(), $reason);
            $this->activityLogs->record(new ActivityLogData(
                tenantId: $tenant->id,
                actorId: $actor->databaseId(),
                action: $decision === TenantOnboardingStatus::Approved ? 'tenant.approved' : 'tenant.rejected',
                subjectType: 'tenant',
                subjectId: $tenant->publicId,
                reason: $reason,
                before: ['onboardingStatus' => $tenant->onboardingStatus, 'operationalStatus' => $tenant->operationalStatus],
                after: ['onboardingStatus' => $decision->value, 'operationalStatus' => $operational->value],
            ));
        });
    }

    private function assertSuperUser(IdentityUser $actor): void
    {
        if ($actor->role() !== UserRole::SuperUser || $actor->status() !== UserStatus::Active) {
            throw new DomainRecordNotFound;
        }
    }
}
