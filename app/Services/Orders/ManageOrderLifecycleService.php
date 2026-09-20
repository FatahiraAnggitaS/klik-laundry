<?php

namespace App\Services\Orders;

use App\Contracts\IdentityUser;
use App\Contracts\TransactionManagerInterface;
use App\DTOs\Audit\ActivityLogData;
use App\DTOs\Orders\OrderData;
use App\Enums\FulfillmentStatus;
use App\Enums\PaymentStatus;
use App\Enums\TenantOnboardingStatus;
use App\Enums\TenantOperationalStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Exceptions\Domain\DomainActionConflict;
use App\Exceptions\Domain\DomainRecordNotFound;
use App\Repositories\Contracts\ActivityLogRepositoryInterface;
use App\Repositories\Contracts\DispatchRepositoryInterface;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Repositories\Contracts\OutletRepositoryInterface;
use App\Repositories\Contracts\TenantRepositoryInterface;
use App\Services\Outlets\CalculateDistanceService;
use Carbon\CarbonImmutable;

final readonly class ManageOrderLifecycleService
{
    public function __construct(
        private OrderRepositoryInterface $orders,
        private OutletRepositoryInterface $outlets,
        private TenantRepositoryInterface $tenants,
        private ValidatePickupScheduleService $schedule,
        private CalculateDistanceService $distance,
        private ActivityLogRepositoryInterface $activityLogs,
        private DispatchRepositoryInterface $dispatch,
        private TransactionManagerInterface $transactions,
    ) {}

    public function cancel(IdentityUser $actor, string $publicId, ?string $reason): void
    {
        $this->assertActor($actor, $reason);
        $this->transactions->run(function () use ($actor, $publicId, $reason): void {
            $order = $this->ownedLockedOrder($actor, $publicId);
            $this->assertMutable($actor, $order, false);
            if ($order->fulfillmentStatus === FulfillmentStatus::PickupAssigned->value) {
                $this->dispatch->cancelForOrder($order->id, $actor->databaseId(), (string) $reason);
            }
            $this->orders->cancel($order->id, $actor->databaseId(), $reason);
            $this->audit($actor, $order, 'order.cancelled', $reason, ['fulfillmentStatus' => $order->fulfillmentStatus], ['fulfillmentStatus' => FulfillmentStatus::Cancelled->value]);
        });
    }

    public function reschedulePickup(IdentityUser $actor, string $publicId, string $slotPublicId, string $date, ?string $reason, ?CarbonImmutable $now = null): void
    {
        $this->assertActor($actor, $reason);
        $this->transactions->run(function () use ($actor, $publicId, $slotPublicId, $date, $reason, $now): void {
            $order = $this->ownedLockedOrder($actor, $publicId);
            $this->assertMutable($actor, $order, true);
            $outlet = $this->outlets->findOwned($order->tenantId, $order->outletPublicId) ?? throw new DomainRecordNotFound;
            foreach ($order->addresses as $address) {
                if ($this->distance->kilometers($outlet->latitude, $outlet->longitude, (float) $address['latitude'], (float) $address['longitude']) * 1000 > $outlet->serviceRadiusMeters) {
                    throw new DomainActionConflict('Order address is outside the current outlet radius.', 'Alamat order berada di luar radius outlet saat ini.');
                }
            }
            $schedule = $this->schedule->handle($outlet, $slotPublicId, $date, $now);
            $this->orders->reschedulePickup($order->id, $schedule['id'], $schedule['startsAt'], $schedule['endsAt'], $actor->databaseId(), $reason);
            $this->audit($actor, $order, 'order.pickup_rescheduled', $reason, ['pickupStartsAt' => $order->pickupStartsAt], ['pickupStartsAt' => $schedule['startsAt']]);
        });
    }

    private function ownedLockedOrder(IdentityUser $actor, string $publicId): OrderData
    {
        $order = $this->orders->lockByPublicId($publicId) ?? throw new DomainRecordNotFound;
        $owned = $actor->role() === UserRole::Customer
            ? $order->customerId === $actor->databaseId()
            : $order->tenantId === $actor->tenantId();
        if (! $owned) {
            throw new DomainRecordNotFound;
        }

        if ($actor->role() === UserRole::TenantOwner) {
            $tenant = $this->tenants->findOwnedByTenantId($actor->tenantId() ?? 0) ?? throw new DomainRecordNotFound;
            if ($tenant->onboardingStatus !== TenantOnboardingStatus::Approved->value || $tenant->operationalStatus === TenantOperationalStatus::Closed->value) {
                throw new DomainRecordNotFound;
            }
        }

        return $order;
    }

    private function assertMutable(IdentityUser $actor, OrderData $order, bool $rescheduling): void
    {
        $statuses = [FulfillmentStatus::AwaitingPayment->value, FulfillmentStatus::AwaitingPickup->value];
        if ($actor->role() === UserRole::TenantOwner) {
            $statuses[] = FulfillmentStatus::PickupAssigned->value;
        }
        if (! in_array($order->fulfillmentStatus, $statuses, true)
            || $order->paymentStatus === PaymentStatus::Paid->value
            || ($rescheduling && $order->fulfillmentStatus === FulfillmentStatus::PickupAssigned->value)) {
            throw new DomainActionConflict('Order can no longer be changed.', 'Order hanya dapat diubah sebelum pickup diterima dan sebelum pembayaran berhasil.');
        }
    }

    private function assertActor(IdentityUser $actor, ?string $reason): void
    {
        if ($actor->status() !== UserStatus::Active || ! in_array($actor->role(), [UserRole::Customer, UserRole::TenantOwner], true)) {
            throw new DomainRecordNotFound;
        }
        if ($actor->role() === UserRole::TenantOwner && ($reason === null || trim($reason) === '')) {
            throw new DomainActionConflict('Tenant reason is required.', 'Alasan wajib diisi untuk perubahan oleh Tenant.');
        }
    }

    /** @param array<string, int|string|null> $before @param array<string, int|string|null> $after */
    private function audit(IdentityUser $actor, OrderData $order, string $action, ?string $reason, array $before, array $after): void
    {
        $this->activityLogs->record(new ActivityLogData($order->tenantId, $actor->databaseId(), $action, 'order', $order->publicId, $reason, $before, $after));
    }
}
