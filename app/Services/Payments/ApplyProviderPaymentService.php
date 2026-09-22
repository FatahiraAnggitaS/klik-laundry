<?php

namespace App\Services\Payments;

use App\Contracts\TransactionManagerInterface;
use App\Enums\FulfillmentStatus;
use App\Enums\PaymentStatus;
use App\Enums\PricingType;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Repositories\Contracts\PaymentRepositoryInterface;

final readonly class ApplyProviderPaymentService
{
    public function __construct(
        private OrderRepositoryInterface $orders,
        private PaymentRepositoryInterface $payments,
        private TransactionManagerInterface $transactions,
    ) {}

    public function handle(string $merchantOrderId, string $providerReference, ?int $feeAmount): void
    {
        $this->transactions->run(function () use ($merchantOrderId, $providerReference, $feeAmount): void {
            $payment = $this->payments->lockByMerchantOrderId($merchantOrderId);

            if ($payment === null) {
                return;
            }

            $applied = $this->payments->applyPaid($merchantOrderId, $providerReference, $feeAmount);

            if (! $applied) {
                return;
            }

            $order = $this->orders->lockByPublicId($payment->orderPublicId);

            if ($order === null || $order->tenantId !== $payment->tenantId) {
                return;
            }

            if ($order->paymentStatus === PaymentStatus::Paid->value) {
                return;
            }

            if ($order->fulfillmentStatus !== FulfillmentStatus::AwaitingPayment->value) {
                $this->orders->syncPaymentStatus($order->id, PaymentStatus::Paid->value);

                return;
            }

            $target = $order->pricingType === PricingType::Fixed->value
                ? FulfillmentStatus::AwaitingPickup->value
                : FulfillmentStatus::Processing->value;

            $this->orders->applyPaidTransition($order->id, null, $target);
        });
    }
}
