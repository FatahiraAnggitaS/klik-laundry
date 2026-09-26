<?php

namespace App\Services\Finance;

use App\Contracts\IdentityUser;
use App\Contracts\TransactionManagerInterface;
use App\DTOs\Audit\ActivityLogData;
use App\DTOs\Finance\RefundData;
use App\Enums\RefundStatus;
use App\Enums\TransferMethod;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Exceptions\Domain\DomainActionConflict;
use App\Exceptions\Domain\DomainRecordNotFound;
use App\Repositories\Contracts\ActivityLogRepositoryInterface;
use App\Repositories\Contracts\RefundRepositoryInterface;

final readonly class ReviewRefundService
{
    public function __construct(private RefundRepositoryInterface $refunds, private ActivityLogRepositoryInterface $activityLogs, private TransactionManagerInterface $transactions) {}

    public function review(IdentityUser $actor, string $publicId, RefundStatus $decision, string $reason): RefundData
    {
        $this->assertActor($actor);
        if (! in_array($decision, [RefundStatus::Approved, RefundStatus::Rejected], true)) {
            throw new DomainActionConflict('Invalid refund review decision.', 'Keputusan review refund tidak valid.');
        }

        return $this->transactions->run(function () use ($actor, $publicId, $decision, $reason): RefundData {
            $refund = $this->refunds->lockForSupport($publicId) ?? throw new DomainRecordNotFound;
            if ($refund->status !== RefundStatus::Submitted->value) {
                throw new DomainActionConflict('Refund is not reviewable.', 'Refund tidak dapat direview pada status saat ini.');
            }
            $updated = $this->refunds->review($refund->id, $decision, $actor->databaseId(), $reason);
            $this->audit($actor, $refund, $updated, $reason);

            return $updated;
        });
    }

    public function complete(IdentityUser $actor, string $publicId, string $method, string $reference, string $reason): RefundData
    {
        $this->assertActor($actor);
        if (TransferMethod::tryFrom($method) === null || $method === TransferMethod::NoTransferRequired->value) {
            throw new DomainActionConflict('Refund completion requires a valid transfer method.', 'Penyelesaian refund membutuhkan metode transfer yang valid.');
        }

        return $this->transactions->run(function () use ($actor, $publicId, $method, $reference, $reason): RefundData {
            $refund = $this->refunds->lockForSupport($publicId) ?? throw new DomainRecordNotFound;
            if ($refund->status !== RefundStatus::Approved->value) {
                throw new DomainActionConflict('Refund is not ready for completion.', 'Refund harus disetujui sebelum diselesaikan.');
            }
            $updated = $this->refunds->complete($refund->id, $actor->databaseId(), $method, $reference, $reason);
            $this->audit($actor, $refund, $updated, $reason);

            return $updated;
        });
    }

    private function assertActor(IdentityUser $actor): void
    {
        if ($actor->role() !== UserRole::SuperUser || $actor->status() !== UserStatus::Active) {
            throw new DomainRecordNotFound;
        }
    }

    private function audit(IdentityUser $actor, RefundData $before, RefundData $after, string $reason): void
    {
        $this->activityLogs->record(new ActivityLogData($before->tenantId, $actor->databaseId(), 'refund.'.$after->status, 'refund_request', $after->publicId, $reason, before: ['status' => $before->status], after: ['status' => $after->status, 'amount' => $after->amount]));
    }
}
