<?php

namespace App\DTOs\Payments;

final readonly class DuitkuSandboxInquiryResult
{
    public function __construct(
        public string $merchantOrderId,
        public string $providerReference,
        public int $amount,
        public string $status,
        public ?int $feeAmount,
        public int $latencyMilliseconds,
    ) {}
}
