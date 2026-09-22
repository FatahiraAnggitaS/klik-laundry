<?php

namespace App\Gateways\Payments;

final class DuitkuCallbackVerifier
{
    public function __construct(
        private readonly DuitkuSignature $signature,
    ) {}

    /**
     * This verifier is an offline Milestone 0 contract probe. It does not mutate payment state.
     *
     * @param  array<string, mixed>  $payload
     */
    public function isValid(
        array $payload,
        string $expectedMerchantCode,
        int $expectedAmount,
        string $expectedMerchantOrderId,
        ?string $expectedProviderReference,
        string $apiKey,
    ): bool {
        foreach (['merchantCode', 'amount', 'merchantOrderId', 'reference', 'signature', 'resultCode'] as $key) {
            if (! isset($payload[$key]) || ! is_scalar($payload[$key])) {
                return false;
            }
        }

        $merchantCode = (string) $payload['merchantCode'];
        $amount = (string) $payload['amount'];
        $merchantOrderId = (string) $payload['merchantOrderId'];
        $reference = (string) $payload['reference'];
        $providedSignature = (string) $payload['signature'];
        $resultCode = (string) $payload['resultCode'];

        if (
            $merchantCode !== $expectedMerchantCode
            || $amount !== (string) $expectedAmount
            || $merchantOrderId !== $expectedMerchantOrderId
            || ($expectedProviderReference !== null && $reference !== $expectedProviderReference)
            || ! in_array($resultCode, ['00', '01'], true)
        ) {
            return false;
        }

        $expectedSignature = $this->signature->forCallback(
            $merchantCode,
            $amount,
            $merchantOrderId,
            $apiKey,
        );

        return hash_equals($expectedSignature, $providedSignature);
    }
}
