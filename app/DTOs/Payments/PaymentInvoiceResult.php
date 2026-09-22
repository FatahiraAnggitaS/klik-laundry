<?php

namespace App\DTOs\Payments;

use DateTimeInterface;

final readonly class PaymentInvoiceResult
{
    public function __construct(
        public string $merchantOrderId,
        public string $providerReference,
        public string $paymentUrl,
        public int $latencyMilliseconds,
        public DateTimeInterface $expiresAt,
    ) {}
}
