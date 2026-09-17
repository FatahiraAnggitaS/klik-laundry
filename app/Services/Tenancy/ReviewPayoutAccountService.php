<?php

namespace App\Services\Tenancy;

use App\Contracts\IdentityUser;
use App\Contracts\TransactionManagerInterface;
use App\DTOs\Audit\ActivityLogData;
use App\Enums\PayoutAccountStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Exceptions\Domain\DomainActionConflict;
use App\Exceptions\Domain\DomainRecordNotFound;
use App\Repositories\Contracts\ActivityLogRepositoryInterface;
use App\Repositories\Contracts\PayoutAccountRepositoryInterface;

final readonly class ReviewPayoutAccountService
{
    public function __construct(
        private PayoutAccountRepositoryInterface $accounts,
        private ActivityLogRepositoryInterface $activityLogs,
        private TransactionManagerInterface $transactions,
    ) {}

    public function handle(IdentityUser $actor, string $accountPublicId, PayoutAccountStatus $decision, string $reason): void
    {
        if ($actor->role() !== UserRole::SuperUser || $actor->status() !== UserStatus::Active) {
            throw new DomainRecordNotFound;
        }

        if (! in_array($decision, [PayoutAccountStatus::Verified, PayoutAccountStatus::Rejected], true)) {
            throw new DomainActionConflict('Invalid payout account review decision.', 'Keputusan rekening payout tidak valid.');
        }

        $this->transactions->run(function () use ($actor, $accountPublicId, $decision, $reason): void {
            $account = $this->accounts->lockByPublicIdForReview($accountPublicId) ?? throw new DomainRecordNotFound;

            if ($account->verificationStatus !== PayoutAccountStatus::Pending->value) {
                throw new DomainActionConflict('Only pending payout accounts can be reviewed.', 'Hanya rekening payout pending yang dapat direview.');
            }

            $this->accounts->review($accountPublicId, $decision, $actor->databaseId(), $reason);
            $this->activityLogs->record(new ActivityLogData(
                tenantId: $account->tenantId,
                actorId: $actor->databaseId(),
                action: $decision === PayoutAccountStatus::Verified ? 'payout_account.verified' : 'payout_account.rejected',
                subjectType: 'tenant_payout_account',
                subjectId: $account->publicId,
                reason: $reason,
                before: ['verificationStatus' => $account->verificationStatus],
                after: ['verificationStatus' => $decision->value],
            ));
        });
    }
}
