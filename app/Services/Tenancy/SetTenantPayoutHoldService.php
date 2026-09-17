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

final readonly class SetTenantPayoutHoldService
{
    public function __construct(
        private TenantRepositoryInterface $tenants,
        private ActivityLogRepositoryInterface $activityLogs,
        private TransactionManagerInterface $transactions,
    ) {}

    public function handle(IdentityUser $actor, string $tenantPublicId, bool $hold, string $reason): void
    {
        if ($actor->role() !== UserRole::SuperUser || $actor->status() !== UserStatus::Active) {
            throw new DomainRecordNotFound;
        }

        $this->transactions->run(function () use ($actor, $tenantPublicId, $hold, $reason): void {
            $tenant = $this->tenants->lockByPublicIdForReview($tenantPublicId) ?? throw new DomainRecordNotFound;

            if ($tenant->operationalStatus === TenantOperationalStatus::Closed->value || $tenant->payoutHold === $hold) {
                throw new DomainActionConflict('Invalid payout hold transition.', 'Perubahan payout hold tidak diizinkan.');
            }

            $this->tenants->setPayoutHold($tenant->id, $hold, $actor->databaseId(), $reason);
            $this->activityLogs->record(new ActivityLogData(
                tenantId: $tenant->id,
                actorId: $actor->databaseId(),
                action: $hold ? 'tenant.payout_hold_set' : 'tenant.payout_hold_released',
                subjectType: 'tenant',
                subjectId: $tenant->publicId,
                reason: $reason,
                before: ['payoutHold' => $tenant->payoutHold],
                after: ['payoutHold' => $hold],
            ));
        });
    }
}
