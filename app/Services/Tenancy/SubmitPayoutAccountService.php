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
use App\Repositories\Contracts\OutletRepositoryInterface;
use App\Repositories\Contracts\PayoutAccountRepositoryInterface;
use App\Repositories\Contracts\TenantPayoutRepositoryInterface;
use App\Repositories\Contracts\TenantRepositoryInterface;

final readonly class SubmitPayoutAccountService
{
    public function __construct(
        private TenantRepositoryInterface $tenants,
        private PayoutAccountRepositoryInterface $accounts,
        private OutletRepositoryInterface $outlets,
        private TenantPayoutRepositoryInterface $tenantPayouts,
        private ActivityLogRepositoryInterface $activityLogs,
        private TransactionManagerInterface $transactions,
    ) {}

    public function handle(IdentityUser $actor, string $bankName, string $holderName, string $accountNumber): void
    {
        $tenantId = $actor->tenantId();

        if ($actor->role() !== UserRole::TenantOwner || $actor->status() !== UserStatus::Active || $tenantId === null) {
            throw new DomainRecordNotFound;
        }

        if (! $actor->hasVerifiedEmailAddress() || ! $actor->hasConfirmedTwoFactorAuthentication()) {
            throw new DomainActionConflict(
                'Verified email and confirmed two-factor authentication are required for payout changes.',
                'Verifikasi email dan aktifkan 2FA sebelum mengubah rekening payout.',
            );
        }

        $this->transactions->run(function () use ($actor, $tenantId, $bankName, $holderName, $accountNumber): void {
            $tenant = $this->tenants->lockOwnedByTenantId($tenantId) ?? throw new DomainRecordNotFound;

            if ($tenant->onboardingStatus !== TenantOnboardingStatus::Approved->value
                || $tenant->operationalStatus !== TenantOperationalStatus::Active->value
                || $tenant->closureRequested) {
                throw new DomainActionConflict('Tenant cannot submit a payout account in its current state.', 'Rekening payout tidak dapat diajukan pada status Tenant saat ini.');
            }

            $previous = $this->accounts->lockCurrentForTenant($tenantId);
            $voidedPayouts = $this->tenantPayouts->voidPendingForAccountChange($tenantId, $actor->databaseId());
            $this->accounts->supersedeCurrent($tenantId);
            $deactivatedOutlets = $this->outlets->deactivateAllForTenant($tenantId);
            $account = $this->accounts->create(
                tenantId: $tenantId,
                submitterId: $actor->databaseId(),
                bankName: $bankName,
                accountHolderName: $holderName,
                accountNumber: $accountNumber,
                maskedAccountNumber: $this->maskAccountNumber($accountNumber),
            );

            $this->activityLogs->record(new ActivityLogData(
                tenantId: $tenantId,
                actorId: $actor->databaseId(),
                action: $previous === null ? 'payout_account.submitted' : 'payout_account.changed',
                subjectType: 'tenant_payout_account',
                subjectId: $account->publicId,
                before: ['verificationStatus' => $previous?->verificationStatus],
                after: [
                    'verificationStatus' => $account->verificationStatus,
                    'deactivatedOutlets' => $deactivatedOutlets,
                    'voidedPendingPayouts' => count($voidedPayouts),
                ],
            ));
        });
    }

    private function maskAccountNumber(string $number): string
    {
        $visible = min(4, strlen($number));

        return str_repeat('*', max(strlen($number) - $visible, 4)).substr($number, -$visible);
    }
}
