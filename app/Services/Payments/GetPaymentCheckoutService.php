<?php

namespace App\Services\Payments;

use App\Contracts\IdentityUser;
use App\DTOs\Payments\PaymentData;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Exceptions\Domain\DomainActionConflict;
use App\Exceptions\Domain\DomainRecordNotFound;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Repositories\Contracts\PaymentRepositoryInterface;

final readonly class GetPaymentCheckoutService
{
    public function __construct(
        private OrderRepositoryInterface $orders,
        private PaymentRepositoryInterface $payments,
    ) {}

    /** @return array<string, mixed> */
    public function forOrder(IdentityUser $customer, string $orderPublicId): array
    {
        if ($customer->role() !== UserRole::Customer || $customer->status() !== UserStatus::Active) {
            throw new DomainRecordNotFound;
        }

        $order = $this->orders->findForCustomer($customer->databaseId(), $orderPublicId) ?? throw new DomainRecordNotFound;

        return [
            'order' => $order->toSummaryArray(),
            'channels' => $this->payments->activeChannels(),
            'activeAttempt' => ($attempt = $this->payments->findPendingForOrder($order->id)) !== null ? $attempt->toCustomerArray() : null,
        ];
    }

    /** @return array<string, mixed> */
    public function forPayment(IdentityUser $customer, string $paymentPublicId): array
    {
        if ($customer->role() !== UserRole::Customer || $customer->status() !== UserStatus::Active) {
            throw new DomainRecordNotFound;
        }

        $payment = $this->payments->lockByPublicIdForCustomer($customer->databaseId(), $paymentPublicId) ?? throw new DomainRecordNotFound;

        return ['payment' => $payment->toCustomerArray()];
    }

    /** @return array<string, mixed> */
    public function receipt(IdentityUser $customer, string $paymentPublicId): array
    {
        $props = $this->forPayment($customer, $paymentPublicId);
        if ($props['payment']['status'] !== PaymentStatus::Paid->value) {
            throw new DomainActionConflict('Receipt is only final after paid.', 'Receipt final tersedia setelah pembayaran berhasil.');
        }

        return $props;
    }

    public function resolveReturn(IdentityUser $customer, string $merchantOrderId): PaymentData
    {
        if ($customer->role() !== UserRole::Customer || $customer->status() !== UserStatus::Active) {
            throw new DomainRecordNotFound;
        }

        return $this->payments->findByMerchantOrderIdForCustomer($customer->databaseId(), $merchantOrderId) ?? throw new DomainRecordNotFound;
    }
}
