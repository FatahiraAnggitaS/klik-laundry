<?php

namespace App\DTOs\Payments;

use DateTimeInterface;

final readonly class PaymentData
{
    public function __construct(
        public int $id,
        public string $publicId,
        public int $tenantId,
        public int $orderId,
        public string $orderPublicId,
        public string $orderNumber,
        public string $merchantOrderId,
        public ?string $providerReference,
        public string $channelCode,
        public string $channelLabel,
        public int $amount,
        public string $status,
        public string $reconciliation,
        public ?string $paymentUrl,
        public ?int $feeAmount,
        public DateTimeInterface $expiresAt,
        public ?DateTimeInterface $paidAt,
    ) {}

    /** @return array<string, mixed> */
    public function toCustomerArray(): array
    {
        return [
            'publicId' => $this->publicId,
            'orderPublicId' => $this->orderPublicId,
            'orderNumber' => $this->orderNumber,
            'merchantOrderId' => $this->merchantOrderId,
            'providerReference' => $this->providerReference,
            'channelCode' => $this->channelCode,
            'channelLabel' => $this->channelLabel,
            'amount' => $this->amount,
            'status' => $this->status,
            'reconciliation' => $this->reconciliation,
            'paymentUrl' => $this->paymentUrl,
            'feeAmount' => $this->feeAmount,
            'expiresAt' => $this->expiresAt->format(DateTimeInterface::ATOM),
            'paidAt' => $this->paidAt?->format(DateTimeInterface::ATOM),
        ];
    }

    /** @return array<string, mixed> */
    public function toOperationalArray(): array
    {
        $data = $this->toCustomerArray();
        unset($data['paymentUrl']);

        return $data;
    }
}
