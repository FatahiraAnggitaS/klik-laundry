<?php

namespace App\DTOs\Customers;

final readonly class CustomerAddressInputData
{
    public function __construct(
        public string $label,
        public string $contactName,
        public string $contactPhone,
        public string $address,
        public string $city,
        public string $area,
        public string $latitude,
        public string $longitude,
        public bool $makeDefault,
    ) {}
}
