<?php

namespace App\DTOs\Customers;

final readonly class CustomerAddressData
{
    public function __construct(
        public int $id,
        public int $customerId,
        public string $publicId,
        public string $label,
        public string $contactName,
        public string $contactPhone,
        public string $address,
        public string $city,
        public string $area,
        public float $latitude,
        public float $longitude,
        public bool $isDefault,
        public string $locationConsentedAt,
    ) {}

    /** @return array<string, bool|float|string> */
    public function toArray(): array
    {
        return [
            'publicId' => $this->publicId,
            'label' => $this->label,
            'contactName' => $this->contactName,
            'contactPhone' => $this->contactPhone,
            'address' => $this->address,
            'city' => $this->city,
            'area' => $this->area,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'isDefault' => $this->isDefault,
            'locationConsentedAt' => $this->locationConsentedAt,
        ];
    }
}
