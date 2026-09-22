<?php

namespace App\Services\Payments;

use App\Contracts\IdentityUser;
use App\Contracts\TransactionManagerInterface;
use App\DTOs\Audit\ActivityLogData;
use App\DTOs\Payments\PaymentData;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Exceptions\Domain\DomainActionConflict;
use App\Exceptions\Domain\DomainRecordNotFound;
use App\Gateways\Payments\PaymentGatewayInterface;
use App\Repositories\Contracts\ActivityLogRepositoryInterface;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Repositories\Contracts\PaymentRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use RuntimeException;

final readonly class InquirePaymentService
{
    private const INQUIRY_COOLDOWN_SECONDS = 30;

    public function __construct(
        private PaymentRepositoryInterface $payments,
        private OrderRepositoryInterface $orders,
        private PaymentGatewayInterface $gateway,
        private ApplyProviderPaymentService $applyPaid,
        private ActivityLogRepositoryInterface $activityLogs,
        private TransactionManagerInterface $transactions,
    ) {}

    public function handle(IdentityUser $actor, string $paymentPublicId, ?CarbonImmutable $now = null): PaymentData
    {
        if ($actor->status() !== UserStatus::Active || $actor->role() !== UserRole::SuperUser) {
            throw new DomainRecordNotFound;
        }

        $payment = $this->payments->findByPublicIdForSupport($paymentPublicId) ?? throw new DomainRecordNotFound;
        $now ??= CarbonImmutable::instance(now());

        $this->transactions->run(function () use ($actor, $payment, $now): void {
            if (! $this->payments->claimInquiry($payment->merchantOrderId, $now->subSeconds(self::INQUIRY_COOLDOWN_SECONDS), $now)) {
                throw new DomainActionConflict('Payment inquiry cooldown is active.', 'Tunggu sebentar sebelum melakukan inquiry ulang.');
            }

            $this->activityLogs->record(new ActivityLogData(
                tenantId: $payment->tenantId,
                actorId: $actor->databaseId(),
                action: 'payment.inquiry_requested',
                subjectType: 'payment',
                subjectId: $payment->publicId,
                reason: 'Rekonsiliasi status provider.',
                before: [],
                after: [],
            ));
        });

        try {
            $result = $this->gateway->inquire($payment->merchantOrderId);
        } catch (RequestException|ConnectionException|RuntimeException) {
            $this->payments->markUncertain($payment->merchantOrderId);

            throw new DomainActionConflict('Duitku inquiry did not produce a trusted result.', 'Status pembayaran sedang diverifikasi.');
        }

        $referenceMatches = $payment->providerReference === null || hash_equals($payment->providerReference, $result->providerReference);
        if (! hash_equals($payment->merchantOrderId, $result->merchantOrderId)
            || $payment->amount !== $result->amount
            || ! $referenceMatches) {
            $this->payments->flagMismatch($payment->merchantOrderId);

            throw new DomainActionConflict('Duitku inquiry identity mismatch.', 'Hasil inquiry tidak cocok dengan tagihan lokal.');
        }

        if ($result->status === 'paid') {
            if (! $this->applyPaid->handle($payment->merchantOrderId, $result->providerReference, $result->feeAmount)) {
                throw new DomainActionConflict('A terminal payment cannot transition to paid.', 'Pembayaran terminal memerlukan pemeriksaan manual.');
            }
        } elseif ($result->status === 'failed_or_expired') {
            $this->transactions->run(function () use ($payment): void {
                $locked = $this->payments->lockByMerchantOrderId($payment->merchantOrderId);
                if ($locked !== null && $this->payments->markTerminal($payment->merchantOrderId, PaymentStatus::Failed->value)) {
                    $order = $this->orders->lockByPublicId($locked->orderPublicId);
                    if ($order !== null && $order->paymentStatus === PaymentStatus::Pending->value) {
                        $this->orders->syncPaymentStatus($order->id, PaymentStatus::Failed->value);
                    }
                }
            });
        } elseif ($result->status === 'unknown') {
            $this->payments->markUncertain($payment->merchantOrderId);
        }

        return $this->payments->findByMerchantOrderId($payment->merchantOrderId) ?? $payment;
    }
}
