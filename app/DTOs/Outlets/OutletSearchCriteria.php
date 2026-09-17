<?php

namespace App\DTOs\Outlets;

use App\Enums\PricingType;

final readonly class OutletSearchCriteria
{
    public function __construct(
        public ?string $query,
        public ?PricingType $pricingType,
        public ?float $latitude,
        public ?float $longitude,
        public int $maximumRadiusMeters,
        public int $perPage = 12,
    ) {}
}
