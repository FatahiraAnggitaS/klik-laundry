<?php

namespace App\DTOs\Payments;

use DateTimeImmutable;

final readonly class DuitkuSandboxCreateResult
{
    public function __construct(
        public string $merchantOrderId,
        public string $providerReference,
        public string $paymentUrl,
        public string $status,
        public int $latencyMilliseconds,
        public DateTimeImmutable $expiresAt,
    ) {}
}
