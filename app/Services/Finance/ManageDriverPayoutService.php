<?php

namespace App\Services\Finance;

use App\Contracts\IdentityUser;
use App\Contracts\TransactionManagerInterface;
use App\DTOs\Audit\ActivityLogData;
use App\DTOs\Finance\DriverPayoutData;
use App\Enums\DriverCommissionStatus;
use App\Enums\PayoutStatus;
use App\Enums\TransferMethod;
use App\Exceptions\Domain\DomainActionConflict;
use App\Exceptions\Domain\DomainRecordNotFound;
use App\Repositories\Contracts\ActivityLogRepositoryInterface;
use App\Repositories\Contracts\DriverPayoutRepositoryInterface;
use App\Services\Tenancy\TenantOperationsGuard;

final readonly class ManageDriverPayoutService
{
    public function __construct(
        private DriverPayoutRepositoryInterface $payouts,
        private TenantOperationsGuard $guard,
        private FinancePeriodService $periods,
        private ActivityLogRepositoryInterface $activityLogs,
        private TransactionManagerInterface $transactions,
    ) {}

    public function create(IdentityUser $actor, string $driverPublicId, string $cutoffDate): DriverPayoutData
    {
        $tenant = $this->guard->forExistingWork($actor);

        return $this->transactions->run(function () use ($actor, $tenant, $driverPublicId, $cutoffDate): DriverPayoutData {
            $driver = $this->payouts->findDriverForTenant($tenant->id, $driverPublicId) ?? throw new DomainRecordNotFound;
            $cutoff = $this->periods->cutoff($cutoffDate);
            $candidates = array_values(array_filter($this->payouts->lockCommissionCandidates($tenant->id, $driver['id'], $cutoff), static fn (array $commission): bool => $commission['status'] === DriverCommissionStatus::Earned->value && ! $commission['claimed']));
            if ($candidates === []) {
                throw new DomainActionConflict('No earned commissions are available.', 'Tidak ada komisi earned yang dapat dibuat menjadi payout.');
            }
            $items = array_map(static fn (array $commission): array => ['id' => $commission['id'], 'amount' => $commission['amount']], $candidates);
            $payout = $this->payouts->create($tenant->id, $driver['id'], $cutoff, $items, $actor->databaseId());
            $this->activityLogs->record(new ActivityLogData($tenant->id, $actor->databaseId(), 'driver_payout.created', 'driver_payout', $payout->publicId, after: ['status' => $payout->status, 'totalAmount' => $payout->totalAmount, 'sourceCount' => count($items)]));

            return $payout;
        });
    }

    public function finalize(IdentityUser $actor, string $publicId, string $method, ?string $reference, ?string $note, string $reason): DriverPayoutData
    {
        $tenant = $this->guard->forExistingWork($actor);

        return $this->transactions->run(function () use ($actor, $tenant, $publicId, $method, $reference, $note, $reason): DriverPayoutData {
            $payout = $this->payouts->lockForTenant($tenant->id, $publicId) ?? throw new DomainRecordNotFound;
            if ($payout->status !== PayoutStatus::PendingTransfer->value) {
                return $payout->status === PayoutStatus::Finalized->value ? $payout : throw new DomainActionConflict('Payout is not finalizable.', 'Payout Driver tidak dapat difinalisasi.');
            }
            if ($payout->totalAmount === 0 && $method !== TransferMethod::NoTransferRequired->value) {
                throw new DomainActionConflict('Zero payout requires no-transfer method.', 'Payout Rp0 harus menggunakan metode tanpa transfer.');
            }
            if ($payout->totalAmount > 0 && ($method === TransferMethod::NoTransferRequired->value || $reference === null || trim($reference) === '')) {
                throw new DomainActionConflict('Positive payout requires transfer reference.', 'Payout positif membutuhkan metode dan referensi transfer.');
            }
            if (TransferMethod::tryFrom($method) === null || ! $this->payouts->sourcesAreFinalizable($payout->id)) {
                throw new DomainActionConflict('Payout sources or transfer method changed.', 'Sumber payout atau metode transfer tidak lagi valid.');
            }
            $updated = $this->payouts->finalize($payout->id, $actor->databaseId(), $method, $reference, $note);
            $this->activityLogs->record(new ActivityLogData($tenant->id, $actor->databaseId(), 'driver_payout.finalized', 'driver_payout', $publicId, $reason, before: ['status' => $payout->status], after: ['status' => $updated->status, 'totalAmount' => $updated->totalAmount]));

            return $updated;
        });
    }

    public function void(IdentityUser $actor, string $publicId, string $reason): DriverPayoutData
    {
        $tenant = $this->guard->forExistingWork($actor);

        return $this->transactions->run(function () use ($actor, $tenant, $publicId, $reason): DriverPayoutData {
            $payout = $this->payouts->lockForTenant($tenant->id, $publicId) ?? throw new DomainRecordNotFound;
            if ($payout->status !== PayoutStatus::PendingTransfer->value) {
                throw new DomainActionConflict('Only pending payouts can be voided.', 'Hanya payout pending yang dapat dibatalkan.');
            }
            $updated = $this->payouts->void($payout->id, $actor->databaseId(), $reason);
            $this->activityLogs->record(new ActivityLogData($tenant->id, $actor->databaseId(), 'driver_payout.voided', 'driver_payout', $publicId, $reason, before: ['status' => $payout->status], after: ['status' => $updated->status]));

            return $updated;
        });
    }
}
