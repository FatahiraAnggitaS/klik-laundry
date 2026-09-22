<?php

namespace App\DTOs\Payments;

final readonly class PaymentInquiryResult
{
    public function __construct(
        public string $merchantOrderId,
        public string $providerReference,
        /** @var 'paid'|'pending'|'failed_or_expired'|'unknown' */
        public string $status,
        public ?int $feeAmount,
        public int $latencyMilliseconds,
    ) {}
}
