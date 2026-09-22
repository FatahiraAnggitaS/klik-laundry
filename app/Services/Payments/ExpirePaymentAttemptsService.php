<?php

namespace App\Services\Payments;

use App\Contracts\TransactionManagerInterface;
use App\Enums\PaymentStatus;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Repositories\Contracts\PaymentRepositoryInterface;
use Carbon\CarbonImmutable;

final readonly class ExpirePaymentAttemptsService
{
    public function __construct(
        private PaymentRepositoryInterface $payments,
        private OrderRepositoryInterface $orders,
        private TransactionManagerInterface $transactions,
    ) {}

    public function handle(?CarbonImmutable $now = null): int
    {
        $now ??= CarbonImmutable::instance(now())->setTimezone('Asia/Jakarta');
        $now = $now->utc();
        $expired = 0;

        foreach ($this->payments->overdueMerchantOrderIds($now) as $merchantOrderId) {
            $changed = $this->transactions->run(function () use ($merchantOrderId, $now): bool {
                $payment = $this->payments->lockByMerchantOrderId($merchantOrderId);
                if ($payment === null
                    || $payment->status !== PaymentStatus::Pending->value
                    || $payment->expiresAt > $now) {
                    return false;
                }

                $order = $this->orders->lockByPublicId($payment->orderPublicId);
                if (! $this->payments->markTerminal($merchantOrderId, PaymentStatus::Expired->value)) {
                    return false;
                }
                if ($order !== null && $order->paymentStatus === PaymentStatus::Pending->value) {
                    $this->orders->syncPaymentStatus($order->id, PaymentStatus::Expired->value);
                }

                return true;
            });

            $expired += $changed ? 1 : 0;
        }

        return $expired;
    }
}
