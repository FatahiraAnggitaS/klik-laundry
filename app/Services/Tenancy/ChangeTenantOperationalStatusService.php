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

final readonly class ChangeTenantOperationalStatusService
{
    public function __construct(
        private TenantRepositoryInterface $tenants,
        private ActivityLogRepositoryInterface $activityLogs,
        private TransactionManagerInterface $transactions,
    ) {}

    public function handle(IdentityUser $actor, string $tenantPublicId, TenantOperationalStatus $target, string $reason): void
    {
        if ($actor->role() !== UserRole::SuperUser || $actor->status() !== UserStatus::Active) {
            throw new DomainRecordNotFound;
        }

        if (! in_array($target, [TenantOperationalStatus::Active, TenantOperationalStatus::Suspended], true)) {
            throw new DomainActionConflict('Unsupported tenant operational transition.', 'Perubahan status Tenant tidak didukung.');
        }

        $this->transactions->run(function () use ($actor, $tenantPublicId, $target, $reason): void {
            $tenant = $this->tenants->lockByPublicIdForReview($tenantPublicId) ?? throw new DomainRecordNotFound;
            $expected = $target === TenantOperationalStatus::Suspended
                ? TenantOperationalStatus::Active
                : TenantOperationalStatus::Suspended;

            if ($tenant->onboardingStatus !== TenantOnboardingStatus::Approved->value || $tenant->operationalStatus !== $expected->value) {
                throw new DomainActionConflict('Invalid tenant operational transition.', 'Perubahan status Tenant tidak diizinkan.');
            }

            $this->tenants->setOperationalStatus($tenant->id, $target, $actor->databaseId(), $reason);
            $this->activityLogs->record(new ActivityLogData(
                tenantId: $tenant->id,
                actorId: $actor->databaseId(),
                action: $target === TenantOperationalStatus::Suspended ? 'tenant.suspended' : 'tenant.reactivated',
                subjectType: 'tenant',
                subjectId: $tenant->publicId,
                reason: $reason,
                before: ['operationalStatus' => $tenant->operationalStatus],
                after: ['operationalStatus' => $target->value],
            ));
        });
    }
}
