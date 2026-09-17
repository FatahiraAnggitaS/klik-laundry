<?php

namespace App\DTOs\Orders;

final readonly class OrderInputData
{
    public function __construct(
        public string $outletPublicId,
        public string $packagePublicId,
        public string $pickupAddressPublicId,
        public ?string $deliveryAddressPublicId,
        public string $pickupSlotPublicId,
        public string $pickupDate,
        public ?int $quantity,
        public ?int $estimatedWeightGrams,
        public string $idempotencyKey,
    ) {}

    /** @return array<string, int|string|null> */
    public function fingerprintPayload(): array
    {
        return [
            'outlet' => $this->outletPublicId,
            'package' => $this->packagePublicId,
            'pickup_address' => $this->pickupAddressPublicId,
            'delivery_address' => $this->deliveryAddressPublicId ?? $this->pickupAddressPublicId,
            'pickup_slot' => $this->pickupSlotPublicId,
            'pickup_date' => $this->pickupDate,
            'quantity' => $this->quantity,
            'estimated_weight_grams' => $this->estimatedWeightGrams,
        ];
    }
}
