<?php

namespace App\DTOs\Tenancy;

final readonly class TenantResubmissionData
{
    public function __construct(
        public string $businessName,
        public string $phone,
        public string $outletName,
        public string $outletAddress,
        public string $city,
        public string $area,
        public string $latitude,
        public string $longitude,
    ) {}
}
