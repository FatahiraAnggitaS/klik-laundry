<?php

namespace App\Services\Finance;

use App\Contracts\IdentityUser;
use App\Contracts\TransactionManagerInterface;
use App\DTOs\Audit\ActivityLogData;
use App\DTOs\Finance\TenantPayoutData;
use App\Enums\PaymentReconciliation;
use App\Enums\PaymentStatus;
use App\Enums\PayoutStatus;
use App\Enums\TransferMethod;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Exceptions\Domain\DomainActionConflict;
use App\Exceptions\Domain\DomainRecordNotFound;
use App\Repositories\Contracts\ActivityLogRepositoryInterface;
use App\Repositories\Contracts\TenantPayoutRepositoryInterface;
use App\Repositories\Contracts\TenantRepositoryInterface;
use Carbon\CarbonImmutable;

final readonly class ManageTenantPayoutService
{
    public function __construct(
        private TenantPayoutRepositoryInterface $payouts,
        private TenantRepositoryInterface $tenants,
        private FinancePeriodService $periods,
        private ActivityLogRepositoryInterface $activityLogs,
        private TransactionManagerInterface $transactions,
    ) {}

    public function create(IdentityUser $actor, string $tenantPublicId, string $cutoffDate): TenantPayoutData
    {
        $this->assertSupport($actor);

        return $this->transactions->run(function () use ($actor, $tenantPublicId, $cutoffDate): TenantPayoutData {
            $tenant = $this->tenants->lockByPublicIdForReview($tenantPublicId) ?? throw new DomainRecordNotFound;
            $cutoff = $this->periods->cutoff($cutoffDate);
            $eligibleBefore = CarbonImmutable::parse($cutoff)->subHours(72);
            $payments = array_values(array_filter($this->payouts->lockPaymentCandidates($tenant->id, $cutoff), static fn (array $payment): bool => $payment['status'] === PaymentStatus::Paid->value
                && $payment['reconciliation'] === PaymentReconciliation::Matched->value
                && $payment['feeAmount'] !== null
                && $payment['completedAt'] !== null
                && CarbonImmutable::parse($payment['completedAt'])->lessThanOrEqualTo($eligibleBefore)
                && ! $payment['hasActiveRefund'] && ! $payment['claimed']));
            $adjustments = array_values(array_filter($this->payouts->lockAdjustmentCandidates($tenant->id, $cutoff), static fn (array $adjustment): bool => ! $adjustment['claimed']));
            $account = $this->payouts->lockVerifiedAccount($tenant->id) ?? throw new DomainActionConflict('Verified payout account is required.', 'Rekening payout aktif harus terverifikasi.');
            $normalizedPayments = array_map(static fn (array $payment): array => ['id' => $payment['id'], 'amount' => $payment['amount'], 'feeAmount' => (int) $payment['feeAmount']], $payments);
            $normalizedAdjustments = array_map(static fn (array $adjustment): array => ['id' => $adjustment['id'], 'amount' => $adjustment['amount']], $adjustments);
            $net = array_sum(array_column($normalizedPayments, 'amount')) - array_sum(array_column($normalizedPayments, 'feeAmount')) + array_sum(array_column($normalizedAdjustments, 'amount'));
            if (($normalizedPayments === [] && $normalizedAdjustments === []) || $net <= 0) {
                throw new DomainActionConflict('No positive payout can be created.', 'Belum ada settlement positif yang dapat dibuat menjadi payout.');
            }
            $payout = $this->payouts->create($tenant->id, $account['id'], $cutoff, $normalizedPayments, $normalizedAdjustments, $account, $actor->databaseId());
            $this->activityLogs->record(new ActivityLogData($tenant->id, $actor->databaseId(), 'tenant_payout.created', 'tenant_payout', $payout->publicId, after: ['status' => $payout->status, 'netAmount' => $payout->netAmount, 'sourceCount' => count($payments) + count($adjustments)]));

            return $payout;
        });
    }

    public function finalize(IdentityUser $actor, string $publicId, string $method, string $reference, string $reason): TenantPayoutData
    {
        $this->assertSupport($actor);

        return $this->transactions->run(function () use ($actor, $publicId, $method, $reference, $reason): TenantPayoutData {
            $payout = $this->payouts->lockForSupport($publicId) ?? throw new DomainRecordNotFound;
            if ($payout->status !== PayoutStatus::PendingTransfer->value) {
                return $payout->status === PayoutStatus::Finalized->value ? $payout : throw new DomainActionConflict('Payout is not finalizable.', 'Payout tidak dapat difinalisasi.');
            }
            $tenant = $this->tenants->lockOwnedByTenantId($payout->tenantId) ?? throw new DomainRecordNotFound;
            if ($method === TransferMethod::NoTransferRequired->value || TransferMethod::tryFrom($method) === null) {
                throw new DomainActionConflict('Tenant payout requires a valid transfer method.', 'Payout Tenant membutuhkan metode transfer yang valid.');
            }
            if ($tenant->payoutHold || ! $this->payouts->accountIsCurrentAndVerified($payout->tenantId, $payout->payoutAccountId)) {
                throw new DomainActionConflict('Payout is held or account changed.', 'Payout ditahan atau rekening sudah berubah.');
            }
            if (! $this->payouts->sourcesAreFinalizable($payout->id)) {
                throw new DomainActionConflict('Payout sources changed before finalization.', 'Sumber payout berubah dan harus ditinjau ulang.');
            }
            $updated = $this->payouts->finalize($payout->id, $actor->databaseId(), $method, $reference);
            $this->activityLogs->record(new ActivityLogData($payout->tenantId, $actor->databaseId(), 'tenant_payout.finalized', 'tenant_payout', $publicId, $reason, before: ['status' => $payout->status], after: ['status' => $updated->status, 'netAmount' => $updated->netAmount]));

            return $updated;
        });
    }

    public function void(IdentityUser $actor, string $publicId, string $reason): TenantPayoutData
    {
        $this->assertSupport($actor);

        return $this->transactions->run(function () use ($actor, $publicId, $reason): TenantPayoutData {
            $payout = $this->payouts->lockForSupport($publicId) ?? throw new DomainRecordNotFound;
            if ($payout->status !== PayoutStatus::PendingTransfer->value) {
                throw new DomainActionConflict('Only pending payouts can be voided.', 'Hanya payout pending yang dapat dibatalkan.');
            }
            $updated = $this->payouts->void($payout->id, $actor->databaseId(), $reason);
            $this->activityLogs->record(new ActivityLogData($payout->tenantId, $actor->databaseId(), 'tenant_payout.voided', 'tenant_payout', $publicId, $reason, before: ['status' => $payout->status], after: ['status' => $updated->status]));

            return $updated;
        });
    }

    private function assertSupport(IdentityUser $actor): void
    {
        if ($actor->role() !== UserRole::SuperUser || $actor->status() !== UserStatus::Active) {
            throw new DomainRecordNotFound;
        }
    }
}
