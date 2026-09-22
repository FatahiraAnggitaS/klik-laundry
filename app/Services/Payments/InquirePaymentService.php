<?php

namespace App\Services\Payments;

use App\Contracts\IdentityUser;
use App\DTOs\Payments\PaymentData;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Exceptions\Domain\DomainActionConflict;
use App\Exceptions\Domain\DomainRecordNotFound;
use App\Gateways\Payments\PaymentGatewayInterface;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Repositories\Contracts\PaymentRepositoryInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use RuntimeException;

final readonly class InquirePaymentService
{
    public function __construct(
        private PaymentRepositoryInterface $payments,
        private OrderRepositoryInterface $orders,
        private PaymentGatewayInterface $gateway,
        private ApplyProviderPaymentService $applyPaid,
    ) {}

    public function handle(IdentityUser $actor, string $merchantOrderId): PaymentData
    {
        if ($actor->status() !== UserStatus::Active || ! in_array($actor->role(), [UserRole::SuperUser, UserRole::TenantOwner], true)) {
            throw new DomainRecordNotFound;
        }

        $payment = $this->payments->lockByMerchantOrderId($merchantOrderId) ?? throw new DomainRecordNotFound;

        if ($actor->role() === UserRole::TenantOwner && $actor->tenantId() !== $payment->tenantId) {
            throw new DomainRecordNotFound;
        }

        try {
            $result = $this->gateway->inquire($merchantOrderId);
        } catch (RequestException|ConnectionException|RuntimeException $exception) {
            $this->payments->markUncertain($merchantOrderId);

            throw new DomainActionConflict('Inquiry tidak pasti: '.$exception->getMessage(), 'Status pembayaran sedang diverifikasi.');
        }

        if ($result->status === 'paid') {
            $this->applyPaid->handle($merchantOrderId, $result->providerReference, $result->feeAmount);
        } elseif ($result->status === 'failed_or_expired') {
            if ($this->payments->markTerminal($merchantOrderId, PaymentStatus::Failed->value)) {
                $this->orders->syncPaymentStatus($payment->orderId, PaymentStatus::Failed->value);
            }
        } elseif ($result->feeAmount !== null) {
            $this->payments->storeFee($merchantOrderId, $result->feeAmount);
        } else {
            $this->payments->markUncertain($merchantOrderId);
        }

        return $this->payments->lockByMerchantOrderId($merchantOrderId) ?? $payment;
    }
}
