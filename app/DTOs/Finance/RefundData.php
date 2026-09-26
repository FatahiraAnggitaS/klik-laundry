<?php

namespace App\DTOs\Finance;

final readonly class RefundData
{
    public function __construct(
        public int $id,
        public string $publicId,
        public int $tenantId,
        public int $orderId,
        public int $paymentId,
        public string $orderNumber,
        public string $paymentPublicId,
        public string $status,
        public int $amount,
        public string $reason,
        public string $submittedAt,
        public ?string $reviewReason,
        public ?string $reviewedAt,
        public ?string $transferMethod,
        public ?string $externalReference,
        public ?string $completedAt,
    ) {}

    /** @return array<string, int|string|null> */
    public function toArray(): array
    {
        $data = get_object_vars($this);
        unset($data['id'], $data['tenantId'], $data['orderId'], $data['paymentId']);

        return $data;
    }
}
