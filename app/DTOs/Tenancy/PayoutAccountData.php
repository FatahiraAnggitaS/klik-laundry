<?php

namespace App\DTOs\Tenancy;

final readonly class PayoutAccountData
{
    public function __construct(
        public string $publicId,
        public int $tenantId,
        public string $bankName,
        public string $maskedHolderName,
        public string $maskedAccountNumber,
        public string $verificationStatus,
        public ?string $reviewReason,
        public string $submittedAt,
        public ?string $reviewedAt,
        public ?string $tenantPublicId = null,
        public ?string $tenantName = null,
    ) {}

    /** @return array<string, int|string|null> */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
