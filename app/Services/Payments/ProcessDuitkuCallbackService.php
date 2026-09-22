<?php

namespace App\Services\Payments;

use App\Contracts\TransactionManagerInterface;
use App\Enums\PaymentStatus;
use App\Gateways\Payments\PaymentGatewayInterface;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Repositories\Contracts\PaymentRepositoryInterface;

final readonly class ProcessDuitkuCallbackService
{
    public function __construct(
        private PaymentRepositoryInterface $payments,
        private OrderRepositoryInterface $orders,
        private PaymentGatewayInterface $gateway,
        private ApplyProviderPaymentService $applyPaid,
        private TransactionManagerInterface $transactions,
    ) {}

    /** @param array<string, mixed> $payload
     *  @return 'paid'|'failed'|'ignored'|'invalid' */
    public function handle(array $payload): string
    {
        $merchantOrderId = isset($payload['merchantOrderId']) && is_scalar($payload['merchantOrderId']) ? (string) $payload['merchantOrderId'] : null;
        $reference = isset($payload['reference']) && is_scalar($payload['reference']) ? (string) $payload['reference'] : null;
        $resultCode = isset($payload['resultCode']) && is_scalar($payload['resultCode']) ? (string) $payload['resultCode'] : null;
        $amount = isset($payload['amount']) && is_numeric($payload['amount']) ? (int) $payload['amount'] : null;

        $fingerprint = hash('sha256', json_encode([$merchantOrderId, $reference, $resultCode, $amount], JSON_THROW_ON_ERROR));

        if ($merchantOrderId === null || $merchantOrderId === '' || $amount === null) {
            $this->payments->recordEvent(null, $fingerprint, $resultCode ?? 'unknown', $reference, $amount, false);

            return 'invalid';
        }

        return $this->transactions->run(function () use ($payload, $merchantOrderId, $reference, $resultCode, $amount, $fingerprint): string {
            $payment = $this->payments->lockByMerchantOrderId($merchantOrderId);

            if ($payment === null) {
                $this->payments->recordEvent(null, $fingerprint, $resultCode ?? 'unknown', $reference, $amount, false);

                return 'invalid';
            }

            $merchantCode = (string) config('services.duitku.merchant_code');
            $apiKey = (string) config('services.duitku.api_key');
            $signatureOk = $this->gateway->verifyCallbackSignature($payload, $merchantCode, $payment->amount, $merchantOrderId, $apiKey, $payment->providerReference);

            if (! $signatureOk || $amount !== $payment->amount) {
                $this->payments->recordEvent($payment->id, $fingerprint, $resultCode ?? 'unknown', $reference, $amount, false);
                $this->payments->flagMismatch($merchantOrderId);

                return 'invalid';
            }

            $firstSeen = $this->payments->recordEvent($payment->id, $fingerprint, $resultCode ?? 'unknown', $reference, $amount, true);

            if (! $firstSeen || $payment->status === PaymentStatus::Paid->value) {
                return $payment->status === PaymentStatus::Paid->value ? 'paid' : 'ignored';
            }

            if ($resultCode === '00') {
                $this->applyPaid->handle($merchantOrderId, $reference ?? $payment->providerReference ?? $merchantOrderId, null);

                return 'paid';
            }

            if ($resultCode === '01') {
                if ($this->payments->markTerminal($merchantOrderId, PaymentStatus::Failed->value)) {
                    $this->orders->syncPaymentStatus($payment->orderId, PaymentStatus::Failed->value);
                }

                return 'failed';
            }

            return 'ignored';
        });
    }
}
