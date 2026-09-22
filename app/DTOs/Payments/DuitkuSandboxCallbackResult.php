<?php

namespace App\DTOs\Payments;

final readonly class DuitkuSandboxCallbackResult
{
    public function __construct(
        public string $paymentPublicId,
        public string $scenario,
        public string $statusBefore,
        public string $statusAfter,
        public int $requestCount,
    ) {}
}
