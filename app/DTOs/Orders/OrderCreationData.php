<?php

namespace App\DTOs\Orders;

final readonly class OrderCreationData
{
    /**
     * @param  array<string, int|string|null>  $item
     * @param  list<array<string, float|string|null>>  $addresses
     */
    public function __construct(
        public string $publicId,
        public string $orderNumber,
        public int $tenantId,
        public int $outletId,
        public int $customerId,
        public string $tenantName,
        public string $outletName,
        public string $idempotencyKey,
        public string $requestFingerprint,
        public string $pricingType,
        public string $fulfillmentStatus,
        public string $paymentStatus,
        public int $pickupSlotId,
        public string $pickupStartsAt,
        public string $pickupEndsAt,
        public ?int $itemsSubtotal,
        public ?int $estimatedItemsSubtotal,
        public int $pickupFee,
        public int $deliveryFee,
        public ?int $grandTotal,
        public ?int $estimatedGrandTotal,
        public ?string $estimatedReadyAt,
        public array $item,
        public array $addresses,
    ) {}
}
