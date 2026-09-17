<?php

namespace App\Services\Outlets;

use App\DTOs\Outlets\OutletSearchCriteria;
use App\Enums\PricingType;
use App\Exceptions\Domain\PlatformSettingsUnavailable;
use App\Repositories\Contracts\OutletRepositoryInterface;
use App\Repositories\Contracts\PlatformSettingRepositoryInterface;

final readonly class SearchOutletsService
{
    public function __construct(
        private OutletRepositoryInterface $outlets,
        private PlatformSettingRepositoryInterface $settings,
    ) {}

    /** @return array<string, mixed> */
    public function handle(?string $query, ?string $pricingType, ?float $latitude, ?float $longitude): array
    {
        $settings = $this->settings->findGlobal() ?? throw new PlatformSettingsUnavailable;
        $criteria = new OutletSearchCriteria(
            query: filled($query) ? trim((string) $query) : null,
            pricingType: filled($pricingType) ? PricingType::from((string) $pricingType) : null,
            latitude: $latitude,
            longitude: $longitude,
            maximumRadiusMeters: $settings->maxServiceRadiusKm * 1000,
        );

        return [
            'outlets' => $this->outlets->search($criteria),
            'filters' => [
                'query' => $criteria->query,
                'pricingType' => $criteria->pricingType?->value,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'usingLocation' => $latitude !== null && $longitude !== null,
            ],
        ];
    }
}
