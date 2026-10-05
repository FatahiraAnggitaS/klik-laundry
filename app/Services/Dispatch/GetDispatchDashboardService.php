<?php

namespace App\Services\Dispatch;

use App\Contracts\IdentityUser;
use App\Repositories\Contracts\DispatchRepositoryInterface;
use App\Repositories\Contracts\DriverRepositoryInterface;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Services\Tenancy\TenantOperationsGuard;

final readonly class GetDispatchDashboardService
{
    public function __construct(private DispatchRepositoryInterface $dispatch, private DriverRepositoryInterface $drivers, private OrderRepositoryInterface $orders, private TenantOperationsGuard $guard) {}

    /** @return array<string, mixed> */
    public function handle(IdentityUser $actor): array
    {
        $tenant = $this->guard->forRead($actor);
        $pickupOrders = $this->orders->paginateForTenant($tenant->id, ['fulfillment_status' => 'awaiting_pickup'], 100)['items'];
        $readyDeliveryOrders = $this->orders->paginateForTenant($tenant->id, ['fulfillment_status' => 'ready_for_delivery'], 100)['items'];
        $scheduledDeliveryOrders = array_values(array_filter(
            $readyDeliveryOrders,
            fn (array $order): bool => $order['deliveryStartsAt'] !== null && $order['deliveryEndsAt'] !== null,
        ));

        return [
            'tenant' => ['name' => $tenant->name, 'operationalStatus' => $tenant->operationalStatus],
            'tasks' => $this->dispatch->paginateForTenant($tenant->id),
            'drivers' => $this->drivers->paginateDrivers($tenant->id, 100)['items'],
            'settings' => $this->drivers->settings($tenant->id)->toArray(),
            'eligibleOrders' => [
                ...$pickupOrders,
                ...$scheduledDeliveryOrders,
            ],
            'waitingDeliveryScheduleCount' => count($readyDeliveryOrders) - count($scheduledDeliveryOrders),
        ];
    }
}
