<?php

namespace App\DTOs\Finance;

final readonly class TenantPayoutData
{
    /** @param list<array<string, int|string|null>> $payments @param list<array<string, int|string|null>> $adjustments */
    public function __construct(
        public int $id,
        public string $publicId,
        public string $batchReference,
        public int $tenantId,
        public int $payoutAccountId,
        public string $status,
        public string $cutoffAt,
        public int $grossAmount,
        public int $feeAmount,
        public int $adjustmentAmount,
        public int $netAmount,
        public string $bankName,
        public string $maskedAccountNumber,
        public ?string $transferMethod,
        public ?string $externalReference,
        public ?string $finalizedAt,
        public ?string $voidReason,
        public array $payments = [],
        public array $adjustments = [],
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $data = get_object_vars($this);
        unset($data['id'], $data['tenantId'], $data['payoutAccountId']);

        return $data;
    }
}
