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

    public function handle(string $merchantOrderId, string $providerReference, ?int $feeAmount): bool
    {
        return $this->transactions->run(function () use ($merchantOrderId, $providerReference, $feeAmount): bool {
            $payment = $this->payments->lockByMerchantOrderId($merchantOrderId);

            if ($payment === null) {
                return false;
            }

            if ($payment->status === PaymentStatus::Paid->value) {
                if ($feeAmount !== null) {
                    $this->payments->storePaidFee($merchantOrderId, $feeAmount);
                }

                return true;
            }

            if ($payment->status !== PaymentStatus::Pending->value) {
                $this->payments->flagMismatch($merchantOrderId);

                return false;
            }

            $order = $this->orders->lockByPublicId($payment->orderPublicId);

            if ($order === null
                || $order->tenantId !== $payment->tenantId
                || $order->fulfillmentStatus !== FulfillmentStatus::AwaitingPayment->value) {
                $this->payments->flagMismatch($merchantOrderId);

                return false;
            }

            $applied = $this->payments->applyPaid($merchantOrderId, $providerReference, $feeAmount);

            if (! $applied) {
                return false;
            }

            $target = $order->pricingType === PricingType::Fixed->value
                ? FulfillmentStatus::AwaitingPickup->value
                : FulfillmentStatus::Processing->value;

            $this->orders->applyPaidTransition($order->id, null, $target);

            return true;
        });
    }
}
