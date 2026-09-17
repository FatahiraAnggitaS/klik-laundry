<?php

namespace App\DTOs\Tenancy;

final readonly class TenantApplicationData
{
    public function __construct(
        public int $id,
        public string $publicId,
        public string $name,
        public string $slug,
        public string $phone,
        public string $onboardingStatus,
        public string $operationalStatus,
        public ?string $reviewReason,
        public ?string $reviewedAt,
        public bool $closureRequested,
        public bool $payoutHold,
        public ?string $payoutHoldReason,
        public string $outletName,
        public string $outletAddress,
        public string $city,
        public string $area,
        public float $latitude,
        public float $longitude,
        public ?string $ownerPublicId = null,
        public ?string $ownerName = null,
        public ?string $ownerEmail = null,
        public ?string $ownerStatus = null,
    ) {}

    /** @return array<string, bool|int|string|null> */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
