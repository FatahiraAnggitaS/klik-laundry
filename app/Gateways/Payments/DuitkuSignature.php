<?php

namespace App\Gateways\Payments;

final class DuitkuSignature
{
    public function forCreateInvoice(string $merchantCode, string $timestamp, string $apiKey): string
    {
        return hash_hmac('sha256', $merchantCode.$timestamp, $apiKey);
    }

    public function forInquiry(string $merchantCode, string $merchantOrderId, string $apiKey): string
    {
        return hash_hmac('sha256', $merchantCode.$merchantOrderId, $apiKey);
    }

    public function forCallback(string $merchantCode, string $amount, string $merchantOrderId, string $apiKey): string
    {
        return hash_hmac('sha256', $merchantCode.$amount.$merchantOrderId, $apiKey);
    }
}
