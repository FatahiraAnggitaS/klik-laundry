<?php

namespace App\DTOs\Finance;

final readonly class DriverPayoutData
{
    /** @param list<array<string, int|string|null>> $items */
    public function __construct(
        public int $id,
        public string $publicId,
        public string $batchReference,
        public int $tenantId,
        public int $driverId,
        public string $driverPublicId,
        public string $driverName,
        public string $status,
        public string $cutoffAt,
        public int $totalAmount,
        public ?string $transferMethod,
        public ?string $externalReference,
        public ?string $note,
        public ?string $finalizedAt,
        public ?string $voidReason,
        public array $items = [],
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $data = get_object_vars($this);
        unset($data['id'], $data['tenantId'], $data['driverId']);

        return $data;
    }
}
