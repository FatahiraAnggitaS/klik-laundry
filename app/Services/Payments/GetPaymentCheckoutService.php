<?php

namespace App\Services\Payments;

use App\Contracts\IdentityUser;
use App\Enums\UserRole;
use App\Enums\UserStatus;
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
            'activeAttempt' => ($attempt = $this->payments->findPendingForOrder($order->id)) !== null ? $attempt->toArray() : null,
        ];
    }

    /** @return array<string, mixed> */
    public function forPayment(IdentityUser $customer, string $paymentPublicId): array
    {
        if ($customer->role() !== UserRole::Customer || $customer->status() !== UserStatus::Active) {
            throw new DomainRecordNotFound;
        }

        $payment = $this->payments->lockByPublicIdForCustomer($customer->databaseId(), $paymentPublicId) ?? throw new DomainRecordNotFound;

        return ['payment' => $payment->toArray()];
    }
}
