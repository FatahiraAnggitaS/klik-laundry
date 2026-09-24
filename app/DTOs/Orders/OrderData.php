<?php

namespace App\DTOs\Orders;

final readonly class OrderData
{
    /**
     * @param  array<string, mixed>  $item
     * @param  list<array<string, mixed>>  $addresses
     * @param  list<array<string, mixed>>  $statusHistory
     * @param  list<array<string, mixed>>  $scheduleHistory
     * @param  list<array<string, mixed>>  $indicators
     */
    public function __construct(
        public int $id,
        public int $tenantId,
        public int $outletId,
        public int $customerId,
        public string $publicId,
        public string $orderNumber,
        public string $requestFingerprint,
        public string $pricingType,
        public string $fulfillmentStatus,
        public string $paymentStatus,
        public int $pickupSlotId,
        public string $pickupSlotPublicId,
        public string $pickupStartsAt,
        public string $pickupEndsAt,
        public ?string $deliveryStartsAt,
        public ?string $deliveryEndsAt,
        public ?int $itemsSubtotal,
        public ?int $estimatedItemsSubtotal,
        public int $pickupFee,
        public int $deliveryFee,
        public ?int $grandTotal,
        public ?int $estimatedGrandTotal,
        public ?string $estimatedReadyAt,
        public ?string $processingStartedAt,
        public ?string $readyAt,
        public ?string $completedAt,
        public ?string $cancelledAt,
        public ?string $cancellationReason,
        public string $outletName,
        public string $outletPublicId,
        public string $tenantName,
        public string $customerName,
        public array $item,
        public array $addresses,
        public array $statusHistory,
        public array $scheduleHistory,
        public array $indicators,
        public string $createdAt,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(bool $includePii = true): array
    {
        $addresses = array_map(function (array $address) use ($includePii): array {
            if ($includePii) {
                return $address;
            }

            return [
                ...$address,
                'contactName' => $this->maskName((string) $address['contactName']),
                'contactPhone' => $this->maskPhone((string) $address['contactPhone']),
                'address' => '[Alamat disamarkan]',
                'latitude' => null,
                'longitude' => null,
            ];
        }, $this->addresses);

        return [
            'publicId' => $this->publicId,
            'orderNumber' => $this->orderNumber,
            'pricingType' => $this->pricingType,
            'fulfillmentStatus' => $this->fulfillmentStatus,
            'paymentStatus' => $this->paymentStatus,
            'pickupStartsAt' => $this->pickupStartsAt,
            'pickupEndsAt' => $this->pickupEndsAt,
            'deliveryStartsAt' => $this->deliveryStartsAt,
            'deliveryEndsAt' => $this->deliveryEndsAt,
            'itemsSubtotal' => $this->itemsSubtotal,
            'estimatedItemsSubtotal' => $this->estimatedItemsSubtotal,
            'pickupFee' => $this->pickupFee,
            'deliveryFee' => $this->deliveryFee,
            'grandTotal' => $this->grandTotal,
            'estimatedGrandTotal' => $this->estimatedGrandTotal,
            'estimatedReadyAt' => $this->estimatedReadyAt,
            'processingStartedAt' => $this->processingStartedAt,
            'readyAt' => $this->readyAt,
            'completedAt' => $this->completedAt,
            'cancelledAt' => $this->cancelledAt,
            'cancellationReason' => $this->cancellationReason,
            'outletName' => $this->outletName,
            'tenantName' => $this->tenantName,
            'customerName' => $includePii ? $this->customerName : $this->maskName($this->customerName),
            'item' => $this->item,
            'addresses' => $addresses,
            'statusHistory' => $this->statusHistory,
            'scheduleHistory' => $this->scheduleHistory,
            'indicators' => $this->indicators,
            'createdAt' => $this->createdAt,
            'isEstimate' => $this->pricingType === 'per_kg',
        ];
    }

    /** @return array<string, mixed> */
    public function toSummaryArray(bool $includePii = true): array
    {
        return array_intersect_key($this->toArray($includePii), array_flip([
            'publicId', 'orderNumber', 'pricingType', 'fulfillmentStatus', 'paymentStatus',
            'pickupStartsAt', 'pickupEndsAt', 'deliveryStartsAt', 'deliveryEndsAt',
            'itemsSubtotal', 'estimatedItemsSubtotal', 'pickupFee', 'deliveryFee',
            'grandTotal', 'estimatedGrandTotal', 'outletName', 'tenantName', 'customerName',
            'item', 'indicators', 'createdAt', 'isEstimate',
        ]));
    }

    private function maskName(string $name): string
    {
        return mb_substr($name, 0, 1).'***';
    }

    private function maskPhone(string $phone): string
    {
        return str_repeat('*', max(0, mb_strlen($phone) - 4)).mb_substr($phone, -4);
    }
}
