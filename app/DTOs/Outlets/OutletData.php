<?php

namespace App\DTOs\Outlets;

final readonly class OutletData
{
    /**
     * @param  list<array{dayOfWeek: int, opensAt: string, closesAt: string}>  $operatingHours
     * @param  list<array{publicId: string, type: string, dayOfWeek: int, startsAt: string, endsAt: string, active: bool}>  $slots
     * @param  list<array{publicId: string, date: string, reason: string}>  $blackouts
     * @param  list<array<string, int|string|null>>  $packages
     */
    public function __construct(
        public int $id,
        public int $tenantId,
        public string $publicId,
        public string $name,
        public string $contactPhone,
        public string $address,
        public string $city,
        public string $area,
        public float $latitude,
        public float $longitude,
        public int $serviceRadiusMeters,
        public int $pickupFee,
        public int $deliveryFee,
        public string $status,
        public array $operatingHours = [],
        public array $slots = [],
        public array $blackouts = [],
        public array $packages = [],
        public ?string $tenantName = null,
        public ?float $distanceKilometers = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'publicId' => $this->publicId,
            'name' => $this->name,
            'contactPhone' => $this->contactPhone,
            'address' => $this->address,
            'city' => $this->city,
            'area' => $this->area,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'serviceRadiusKm' => $this->serviceRadiusMeters / 1000,
            'pickupFee' => $this->pickupFee,
            'deliveryFee' => $this->deliveryFee,
            'status' => $this->status,
            'operatingHours' => $this->operatingHours,
            'slots' => $this->slots,
            'blackouts' => $this->blackouts,
            'packages' => $this->packages,
            'tenantName' => $this->tenantName,
            'distanceKm' => $this->distanceKilometers,
        ];
    }
}
