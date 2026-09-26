<?php

namespace App\Services\Finance;

use App\Contracts\IdentityUser;
use App\Contracts\TransactionManagerInterface;
use App\DTOs\Audit\ActivityLogData;
use App\DTOs\Finance\RefundData;
use App\Enums\PaymentStatus;
use App\Exceptions\Domain\DomainActionConflict;
use App\Exceptions\Domain\DomainRecordNotFound;
use App\Repositories\Contracts\ActivityLogRepositoryInterface;
use App\Repositories\Contracts\RefundRepositoryInterface;
use App\Services\Tenancy\TenantOperationsGuard;
use Carbon\CarbonImmutable;

final readonly class SubmitRefundRequestService
{
    public function __construct(
        private RefundRepositoryInterface $refunds,
        private TenantOperationsGuard $guard,
        private ActivityLogRepositoryInterface $activityLogs,
        private TransactionManagerInterface $transactions,
    ) {}

    public function handle(IdentityUser $actor, string $paymentPublicId, string $reason): RefundData
    {
        $tenant = $this->guard->forExistingWork($actor);

        return $this->transactions->run(function () use ($actor, $tenant, $paymentPublicId, $reason): RefundData {
            $source = $this->refunds->lockSourceForTenant($tenant->id, $paymentPublicId) ?? throw new DomainRecordNotFound;
            if ($source['paymentStatus'] !== PaymentStatus::Paid->value || $source['completedAt'] === null) {
                throw new DomainActionConflict('Refund source is not eligible.', 'Refund hanya dapat diajukan untuk order selesai dengan pembayaran berhasil.');
            }
            if (CarbonImmutable::parse($source['completedAt'])->addHours(72)->isPast()) {
                throw new DomainActionConflict('Refund deadline has passed.', 'Batas pengajuan refund 3×24 jam telah lewat.');
            }
            if ($this->refunds->hasActiveOrCompletedForPayment($source['paymentId'])) {
                throw new DomainActionConflict('Payment already has an active or completed refund.', 'Payment sudah memiliki refund aktif atau selesai.');
            }
            $refund = $this->refunds->create($tenant->id, $source['orderId'], $source['paymentId'], $source['amount'], $reason, $actor->databaseId());
            $this->activityLogs->record(new ActivityLogData($tenant->id, $actor->databaseId(), 'refund.submitted', 'refund_request', $refund->publicId, $reason, after: ['status' => $refund->status, 'amount' => $refund->amount]));

            return $refund;
        });
    }
}
