<?php

namespace App\DTOs\Outlets;

final readonly class OutletInputData
{
    public function __construct(
        public string $name,
        public string $contactPhone,
        public string $address,
        public string $city,
        public string $area,
        public string $latitude,
        public string $longitude,
        public int $serviceRadiusKm,
        public int $pickupFee,
        public int $deliveryFee,
    ) {}
}
