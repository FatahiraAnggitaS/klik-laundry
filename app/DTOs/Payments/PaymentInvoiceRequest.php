<?php

namespace App\DTOs\Payments;

use DateTimeInterface;

final readonly class PaymentInvoiceRequest
{
    public function __construct(
        public string $merchantOrderId,
        public int $amount,
        public string $channelCode,
        public string $customerEmail,
        public string $callbackUrl,
        public string $returnUrl,
        public DateTimeInterface $expiresAt,
    ) {}
}
