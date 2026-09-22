<?php

namespace App\Gateways\Payments;

use App\DTOs\Payments\PaymentInquiryResult;
use App\DTOs\Payments\PaymentInvoiceRequest;
use App\DTOs\Payments\PaymentInvoiceResult;

interface PaymentGatewayInterface
{
    public function createInvoice(PaymentInvoiceRequest $invoice): PaymentInvoiceResult;

    public function inquire(string $merchantOrderId): PaymentInquiryResult;

    public function verifyCallbackSignature(array $payload, string $merchantCode, int $amount, string $merchantOrderId, string $apiKey, ?string $expectedProviderReference = null): bool;
}
