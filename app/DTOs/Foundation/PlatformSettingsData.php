<?php

namespace App\DTOs\Foundation;

use DateTimeInterface;

final readonly class PlatformSettingsData
{
    public function __construct(
        public int $maxServiceRadiusKm,
        public bool $paymentMaintenanceEnabled,
        public int $version,
        public ?DateTimeInterface $updatedAt,
    ) {}

    /**
     * @return array{
     *     maxServiceRadiusKm: int,
     *     paymentMaintenanceEnabled: bool,
     *     version: int,
     *     updatedAt: ?string
     * }
     */
    public function toArray(): array
    {
        return [
            'maxServiceRadiusKm' => $this->maxServiceRadiusKm,
            'paymentMaintenanceEnabled' => $this->paymentMaintenanceEnabled,
            'version' => $this->version,
            'updatedAt' => $this->updatedAt?->format(DateTimeInterface::ATOM),
        ];
    }
}
