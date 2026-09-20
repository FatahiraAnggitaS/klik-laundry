<?php

namespace App\DTOs\Dispatch;

final readonly class DriverSettingsData
{
    public function __construct(
        public int $tenantId,
        public ?int $pickupCommission,
        public ?int $deliveryCommission,
    ) {}

    /** @return array{pickupCommission: int|null, deliveryCommission: int|null} */
    public function toArray(): array
    {
        return [
            'pickupCommission' => $this->pickupCommission,
            'deliveryCommission' => $this->deliveryCommission,
        ];
    }
}
