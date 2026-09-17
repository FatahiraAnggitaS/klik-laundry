<?php

namespace App\DTOs\Tenancy;

final readonly class TenantRegistrationData
{
    public function __construct(
        public string $businessName,
        public string $ownerName,
        public string $email,
        public string $phone,
        public string $password,
        public string $outletName,
        public string $outletAddress,
        public string $city,
        public string $area,
        public string $latitude,
        public string $longitude,
    ) {}
}
