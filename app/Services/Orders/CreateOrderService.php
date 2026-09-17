<?php

namespace App\Services\Orders;

use App\Contracts\IdentityUser;
use App\Contracts\TransactionManagerInterface;
use App\DTOs\Audit\ActivityLogData;
use App\DTOs\Customers\CustomerAddressData;
use App\DTOs\Orders\OrderCreationData;
use App\DTOs\Orders\OrderData;
use App\DTOs\Orders\OrderInputData;
use App\Enums\FulfillmentStatus;
use App\Enums\OrderAddressType;
use App\Enums\PaymentStatus;
use App\Enums\PricingType;
use App\Enums\ResourceStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Exceptions\Domain\DomainActionConflict;
use App\Exceptions\Domain\DomainRecordNotFound;
use App\Exceptions\Domain\PlatformSettingsUnavailable;
use App\Repositories\Contracts\ActivityLogRepositoryInterface;
use App\Repositories\Contracts\CustomerAddressRepositoryInterface;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Repositories\Contracts\OutletRepositoryInterface;
use App\Repositories\Contracts\PackageRepositoryInterface;
use App\Repositories\Contracts\PlatformSettingRepositoryInterface;
use App\Services\Outlets\CalculateDistanceService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

final readonly class CreateOrderService
{
    public function __construct(
        private OrderRepositoryInterface $orders,
        private OutletRepositoryInterface $outlets,
        private PackageRepositoryInterface $packages,
        private CustomerAddressRepositoryInterface $addresses,
        private PlatformSettingRepositoryInterface $settings,
        private CalculateDistanceService $distance,
        private ValidatePickupScheduleService $schedule,
        private ActivityLogRepositoryInterface $activityLogs,
        private TransactionManagerInterface $transactions,
    ) {}

    public function handle(IdentityUser $actor, OrderInputData $input, ?CarbonImmutable $now = null): OrderData
    {
        if ($actor->role() !== UserRole::Customer || $actor->status() !== UserStatus::Active || ! $actor->hasVerifiedEmailAddress()) {
            throw new DomainRecordNotFound;
        }

        $fingerprint = hash('sha256', json_encode($input->fingerprintPayload(), JSON_THROW_ON_ERROR));
        $existing = $this->orders->findIdempotent($actor->databaseId(), $input->idempotencyKey);
        if ($existing !== null) {
            return $this->assertSameRequest($existing, $fingerprint);
        }

        return $this->transactions->run(function () use ($actor, $input, $fingerprint, $now): OrderData {
            $setting = $this->settings->findGlobal() ?? throw new PlatformSettingsUnavailable;
            $outlet = $this->outlets->findDiscoverable($input->outletPublicId, $setting->maxServiceRadiusKm * 1000) ?? throw new DomainRecordNotFound;
            $package = $this->packages->findOwned($outlet->tenantId, $input->packagePublicId) ?? throw new DomainRecordNotFound;
            if ($package->status !== ResourceStatus::Active->value) {
                throw new DomainRecordNotFound;
            }

            $pickup = $this->addresses->lockOwned($actor->databaseId(), $input->pickupAddressPublicId) ?? throw new DomainRecordNotFound;
            $delivery = $input->deliveryAddressPublicId === null
                ? $pickup
                : ($this->addresses->lockOwned($actor->databaseId(), $input->deliveryAddressPublicId) ?? throw new DomainRecordNotFound);
            $this->assertCovered($outlet->latitude, $outlet->longitude, $outlet->serviceRadiusMeters, $pickup);
            $this->assertCovered($outlet->latitude, $outlet->longitude, $outlet->serviceRadiusMeters, $delivery);
            $schedule = $this->schedule->handle($outlet, $input->pickupSlotPublicId, $input->pickupDate, $now);

            $isFixed = $package->pricingType === PricingType::Fixed->value;
            $quantity = $isFixed ? $input->quantity : null;
            if ($isFixed && ($quantity === null || $quantity < ($package->minimumQuantity ?? 1))) {
                throw new DomainActionConflict('Fixed quantity is invalid.', 'Jumlah paket fixed belum memenuhi minimum.');
            }
            if (! $isFixed && $input->quantity !== null) {
                throw new DomainActionConflict('Quantity is not valid for per-kg pricing.', 'Quantity hanya boleh digunakan untuk paket fixed.');
            }

            $billableEstimate = null;
            $estimatedSubtotal = null;
            if (! $isFixed && $input->estimatedWeightGrams !== null) {
                if ($input->estimatedWeightGrams < 1) {
                    throw new DomainActionConflict('Estimated weight is invalid.', 'Estimasi berat harus lebih dari nol.');
                }
                $billableEstimate = (int) (ceil(max($input->estimatedWeightGrams, $package->minimumWeightGrams ?? 1) / 100) * 100);
                $estimatedSubtotal = intdiv($package->unitPrice * $billableEstimate, 1000);
            }
            $subtotal = $isFixed ? $package->unitPrice * (int) $quantity : null;
            $grandTotal = $subtotal === null ? null : $subtotal + $outlet->pickupFee + $outlet->deliveryFee;
            $estimatedGrandTotal = $estimatedSubtotal === null ? null : $estimatedSubtotal + $outlet->pickupFee + $outlet->deliveryFee;
            $ulid = (string) Str::ulid();
            $jakartaDate = ($now ?? CarbonImmutable::now('Asia/Jakarta'))->format('Ymd');

            $result = $this->orders->createOrFind(new OrderCreationData(
                publicId: $ulid,
                orderNumber: "KL-{$jakartaDate}-{$ulid}",
                tenantId: $outlet->tenantId,
                outletId: $outlet->id,
                customerId: $actor->databaseId(),
                tenantName: $outlet->tenantName ?? 'Tenant',
                outletName: $outlet->name,
                idempotencyKey: $input->idempotencyKey,
                requestFingerprint: $fingerprint,
                pricingType: $package->pricingType,
                fulfillmentStatus: $isFixed ? FulfillmentStatus::AwaitingPayment->value : FulfillmentStatus::AwaitingPickup->value,
                paymentStatus: PaymentStatus::Unpaid->value,
                pickupSlotId: $schedule['id'],
                pickupStartsAt: $schedule['startsAt'],
                pickupEndsAt: $schedule['endsAt'],
                itemsSubtotal: $subtotal,
                estimatedItemsSubtotal: $estimatedSubtotal,
                pickupFee: $outlet->pickupFee,
                deliveryFee: $outlet->deliveryFee,
                grandTotal: $grandTotal,
                estimatedGrandTotal: $estimatedGrandTotal,
                estimatedReadyAt: CarbonImmutable::parse($schedule['endsAt'])->addMinutes($package->estimatedDurationMinutes)->toIso8601String(),
                item: [
                    'package_id' => $package->id, 'package_name' => $package->name, 'package_description' => $package->description,
                    'pricing_type' => $package->pricingType, 'unit_price' => $package->unitPrice, 'minimum_quantity' => $package->minimumQuantity,
                    'minimum_weight_grams' => $package->minimumWeightGrams, 'estimated_duration_minutes' => $package->estimatedDurationMinutes,
                    'quantity' => $quantity, 'estimated_weight_grams' => $input->estimatedWeightGrams, 'estimated_billable_weight_grams' => $billableEstimate,
                ],
                addresses: [$this->addressSnapshot(OrderAddressType::Pickup, $pickup), $this->addressSnapshot(OrderAddressType::Delivery, $delivery)],
            ));
            $order = $this->assertSameRequest($result['order'], $fingerprint);
            if ($result['created']) {
                $this->activityLogs->record(new ActivityLogData($outlet->tenantId, $actor->databaseId(), 'order.created', 'order', $order->publicId, after: ['fulfillmentStatus' => $order->fulfillmentStatus, 'pricingType' => $order->pricingType]));
            }

            return $order;
        });
    }

    private function assertSameRequest(OrderData $order, string $fingerprint): OrderData
    {
        if (! hash_equals($order->requestFingerprint, $fingerprint)) {
            throw new DomainActionConflict('Idempotency key was reused with a different payload.', 'Token formulir sudah digunakan untuk data order berbeda. Muat ulang halaman.');
        }

        return $order;
    }

    private function assertCovered(float $latitude, float $longitude, int $radius, CustomerAddressData $address): void
    {
        if ($this->distance->kilometers($latitude, $longitude, $address->latitude, $address->longitude) * 1000 > $radius) {
            throw new DomainActionConflict('Address is outside the outlet radius.', 'Alamat pickup dan delivery harus berada dalam radius outlet.');
        }
    }

    /** @return array<string, float|string|null> */
    private function addressSnapshot(OrderAddressType $type, CustomerAddressData $address): array
    {
        return ['type' => $type->value, 'source_public_id' => $address->publicId, 'label' => $address->label, 'contact_name' => $address->contactName, 'contact_phone' => $address->contactPhone, 'address' => $address->address, 'city' => $address->city, 'area' => $address->area, 'latitude' => $address->latitude, 'longitude' => $address->longitude];
    }
}
