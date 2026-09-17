<?php

namespace App\DTOs\Catalog;

use App\Enums\PricingType;

final readonly class PackageInputData
{
    public function __construct(
        public string $name,
        public ?string $description,
        public PricingType $pricingType,
        public int $unitPrice,
        public ?int $minimumQuantity,
        public ?int $minimumWeightGrams,
        public int $estimatedDurationMinutes,
    ) {}
}
