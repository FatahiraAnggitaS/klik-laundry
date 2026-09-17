<?php

namespace App\DTOs\Catalog;

final readonly class PackageData
{
    public function __construct(
        public int $id,
        public int $tenantId,
        public string $publicId,
        public string $name,
        public ?string $description,
        public string $pricingType,
        public int $unitPrice,
        public ?int $minimumQuantity,
        public ?int $minimumWeightGrams,
        public int $estimatedDurationMinutes,
        public string $status,
    ) {}

    /** @return array<string, int|string|null> */
    public function toArray(): array
    {
        return [
            'publicId' => $this->publicId,
            'name' => $this->name,
            'description' => $this->description,
            'pricingType' => $this->pricingType,
            'unitPrice' => $this->unitPrice,
            'minimumQuantity' => $this->minimumQuantity,
            'minimumWeightGrams' => $this->minimumWeightGrams,
            'estimatedDurationMinutes' => $this->estimatedDurationMinutes,
            'status' => $this->status,
        ];
    }
}
