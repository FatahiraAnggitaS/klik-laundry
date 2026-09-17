<?php

namespace App\Services\Outlets;

use App\Contracts\IdentityUser;
use App\DTOs\Customers\CustomerAddressData;
use App\Enums\UserRole;
use App\Exceptions\Domain\DomainRecordNotFound;
use App\Exceptions\Domain\PlatformSettingsUnavailable;
use App\Repositories\Contracts\CustomerAddressRepositoryInterface;
use App\Repositories\Contracts\OutletRepositoryInterface;
use App\Repositories\Contracts\PlatformSettingRepositoryInterface;
use Illuminate\Support\Str;

final readonly class GetOutletDetailsService
{
    public function __construct(
        private OutletRepositoryInterface $outlets,
        private CustomerAddressRepositoryInterface $addresses,
        private PlatformSettingRepositoryInterface $settings,
        private CalculateDistanceService $distance,
        private GetAvailableScheduleService $schedule,
    ) {}

    /** @return array<string, mixed> */
    public function handle(string $outletPublicId, ?IdentityUser $actor, ?string $pickupAddressId, ?string $deliveryAddressId): array
    {
        $settings = $this->settings->findGlobal() ?? throw new PlatformSettingsUnavailable;
        $outlet = $this->outlets->findDiscoverable($outletPublicId, $settings->maxServiceRadiusKm * 1000)
            ?? throw new DomainRecordNotFound;
        $savedAddresses = [];
        $coverage = ['pickup' => null, 'delivery' => null, 'ready' => false];

        if ($actor?->role() === UserRole::Customer) {
            $savedAddresses = $this->addresses->listOwned($actor->databaseId());
            $pickup = $pickupAddressId === null ? null : $this->addresses->findOwned($actor->databaseId(), $pickupAddressId);
            $delivery = $deliveryAddressId === null ? null : $this->addresses->findOwned($actor->databaseId(), $deliveryAddressId);

            if (($pickupAddressId !== null && $pickup === null) || ($deliveryAddressId !== null && $delivery === null)) {
                throw new DomainRecordNotFound;
            }

            $coverage = [
                'pickup' => $this->coverage($outlet->latitude, $outlet->longitude, $outlet->serviceRadiusMeters, $pickup),
                'delivery' => $this->coverage($outlet->latitude, $outlet->longitude, $outlet->serviceRadiusMeters, $delivery),
                'ready' => $pickup !== null && $delivery !== null
                    && $this->isCovered($outlet->latitude, $outlet->longitude, $outlet->serviceRadiusMeters, $pickup)
                    && $this->isCovered($outlet->latitude, $outlet->longitude, $outlet->serviceRadiusMeters, $delivery),
            ];
        }

        return [
            'outlet' => [
                'publicId' => $outlet->publicId,
                'name' => $outlet->name,
                'contactPhone' => $outlet->contactPhone,
                'address' => $outlet->address,
                'city' => $outlet->city,
                'area' => $outlet->area,
                'serviceRadiusKm' => $outlet->serviceRadiusMeters / 1000,
                'pickupFee' => $outlet->pickupFee,
                'deliveryFee' => $outlet->deliveryFee,
                'packages' => $outlet->packages,
                'tenantName' => $outlet->tenantName,
                'distanceKm' => null,
            ],
            'availableSlots' => $this->schedule->handle($outlet),
            'savedAddresses' => $savedAddresses,
            'coverage' => $coverage,
            'checkoutToken' => $actor?->role() === UserRole::Customer ? (string) Str::uuid() : null,
        ];
    }

    /** @return array{addressPublicId: string, distanceKm: float, withinRadius: bool}|null */
    private function coverage(float $latitude, float $longitude, int $radiusMeters, ?CustomerAddressData $address): ?array
    {
        if ($address === null) {
            return null;
        }

        $distance = $this->distance->kilometers($latitude, $longitude, $address->latitude, $address->longitude);

        return [
            'addressPublicId' => $address->publicId,
            'distanceKm' => round($distance, 2),
            'withinRadius' => ($distance * 1000) <= $radiusMeters,
        ];
    }

    private function isCovered(float $latitude, float $longitude, int $radiusMeters, CustomerAddressData $address): bool
    {
        return ($this->distance->kilometers($latitude, $longitude, $address->latitude, $address->longitude) * 1000) <= $radiusMeters;
    }
}
