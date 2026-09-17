<?php

namespace App\Services\Orders;

use App\Contracts\TransactionManagerInterface;
use App\DTOs\Orders\OrderData;
use App\Enums\FulfillmentStatus;
use App\Enums\OrderIndicatorType;
use App\Repositories\Contracts\OrderRepositoryInterface;
use Carbon\CarbonImmutable;

final readonly class MonitorOrderIndicatorsService
{
    public function __construct(private OrderRepositoryInterface $orders, private TransactionManagerInterface $transactions) {}

    public function handle(?CarbonImmutable $now = null): int
    {
        $now ??= CarbonImmutable::now('Asia/Jakarta');
        $count = 0;
        foreach ($this->orders->monitoringCandidates() as $order) {
            $this->transactions->run(function () use ($order, $now, &$count): void {
                foreach ($this->states($order, $now) as $type => $active) {
                    $this->orders->syncIndicator($order->id, OrderIndicatorType::from($type), $active, $now->utc()->toIso8601String(), ['status' => $order->fulfillmentStatus]);
                    if ($active) {
                        $count++;
                    }
                }
            });
        }

        return $count;
    }

    /** @return array<string, bool> */
    private function states(OrderData $order, CarbonImmutable $now): array
    {
        $pickupDelayed = $order->fulfillmentStatus === FulfillmentStatus::AwaitingPickup->value && CarbonImmutable::parse($order->pickupEndsAt)->lessThan($now);
        $delayed = $order->fulfillmentStatus === FulfillmentStatus::Processing->value && $order->estimatedReadyAt !== null && CarbonImmutable::parse($order->estimatedReadyAt)->lessThan($now);
        $deliveryDelayed = $order->fulfillmentStatus === FulfillmentStatus::ReadyForDelivery->value && $order->deliveryEndsAt !== null && CarbonImmutable::parse($order->deliveryEndsAt)->lessThan($now);
        $awaitingCustomer = $order->fulfillmentStatus === FulfillmentStatus::ReadyForDelivery->value && $order->deliveryStartsAt === null && $order->readyAt !== null && CarbonImmutable::parse($order->readyAt)->addDays(7)->lessThanOrEqualTo($now);

        return [OrderIndicatorType::PickupDelayed->value => $pickupDelayed, OrderIndicatorType::Delayed->value => $delayed, OrderIndicatorType::DeliveryDelayed->value => $deliveryDelayed, OrderIndicatorType::AwaitingCustomer->value => $awaitingCustomer];
    }
}
