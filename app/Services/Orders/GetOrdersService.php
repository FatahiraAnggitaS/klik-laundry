<?php

namespace App\Services\Orders;

use App\Contracts\IdentityUser;
use App\DTOs\Orders\OrderData;
use App\Enums\FulfillmentStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Exceptions\Domain\DomainRecordNotFound;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Repositories\Contracts\OutletRepositoryInterface;
use App\Services\Outlets\GetAvailableScheduleService;
use Carbon\CarbonImmutable;

final readonly class GetOrdersService
{
    public function __construct(private OrderRepositoryInterface $orders, private OutletRepositoryInterface $outlets, private GetAvailableScheduleService $schedule) {}

    /** @param array{fulfillment_status?: string|null, payment_status?: string|null, query?: string|null} $filters @return array<string, mixed> */
    public function list(IdentityUser $actor, array $filters): array
    {
        $this->assertActor($actor);
        $orders = $actor->role() === UserRole::Customer
            ? $this->orders->paginateForCustomer($actor->databaseId(), $filters)
            : $this->orders->paginateForTenant($actor->tenantId() ?? 0, $filters);

        return [
            'orders' => $orders,
            'filters' => $filters,
            'viewer' => $actor->role()->value,
            'statusOptions' => array_map(fn (FulfillmentStatus $status): array => ['value' => $status->value, 'label' => str($status->value)->replace('_', ' ')->title()->toString()], FulfillmentStatus::cases()),
        ];
    }

    /** @return array<string, mixed> */
    public function detail(IdentityUser $actor, string $publicId, bool $receipt = false): array
    {
        $this->assertActor($actor);
        $order = $this->find($actor, $publicId);
        $includePii = $actor->role() === UserRole::Customer || $this->tenantMaySeePii($order);

        $outlet = $this->outlets->findOwned($order->tenantId, $order->outletPublicId);
        $allSlots = $outlet === null ? [] : $this->schedule->handle($outlet);
        $availableSlots = array_values(array_filter($allSlots, fn (array $slot): bool => $slot['type'] === 'pickup'));
        $deliveryCutoff = $order->readyAt === null ? null : CarbonImmutable::parse($order->readyAt)->addDays(7);
        $availableDeliverySlots = array_values(array_filter($allSlots, fn (array $slot): bool => $slot['type'] === 'delivery'
            && ($deliveryCutoff === null || $actor->role() === UserRole::TenantOwner || CarbonImmutable::parse($slot['startsAt'])->lessThanOrEqualTo($deliveryCutoff))));
        $canScheduleDelivery = $order->fulfillmentStatus === FulfillmentStatus::ReadyForDelivery->value
            && ($actor->role() === UserRole::Customer
                ? $deliveryCutoff?->greaterThanOrEqualTo(now()) === true
                : ($order->deliveryStartsAt !== null || $deliveryCutoff?->lessThanOrEqualTo(now()) === true));

        return [
            'order' => $order->toArray($includePii),
            'viewer' => $actor->role()->value,
            'receipt' => $receipt,
            'availablePickupSlots' => $availableSlots,
            'availableDeliverySlots' => $availableDeliverySlots,
            'canMutate' => in_array($order->fulfillmentStatus, [FulfillmentStatus::AwaitingPayment->value, FulfillmentStatus::AwaitingPickup->value], true) && $order->paymentStatus !== 'paid',
            'canMarkReady' => $actor->role() === UserRole::TenantOwner && $order->fulfillmentStatus === FulfillmentStatus::Processing->value && $order->paymentStatus === 'paid',
            'canScheduleDelivery' => $canScheduleDelivery,
        ];
    }

    private function find(IdentityUser $actor, string $publicId): OrderData
    {
        return ($actor->role() === UserRole::Customer
            ? $this->orders->findForCustomer($actor->databaseId(), $publicId)
            : $this->orders->findForTenant($actor->tenantId() ?? 0, $publicId)) ?? throw new DomainRecordNotFound;
    }

    private function assertActor(IdentityUser $actor): void
    {
        if ($actor->status() !== UserStatus::Active || ! in_array($actor->role(), [UserRole::Customer, UserRole::TenantOwner], true)) {
            throw new DomainRecordNotFound;
        }
    }

    private function tenantMaySeePii(OrderData $order): bool
    {
        if ($order->fulfillmentStatus === FulfillmentStatus::Cancelled->value) {
            return false;
        }
        if ($order->fulfillmentStatus !== FulfillmentStatus::Completed->value) {
            return true;
        }

        return $order->completedAt !== null && CarbonImmutable::parse($order->completedAt)->greaterThanOrEqualTo(now()->subHours(72));
    }
}
