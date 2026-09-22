<?php

namespace App\Repositories\Contracts;

use App\DTOs\Payments\PaymentData;
use DateTimeInterface;

interface PaymentRepositoryInterface
{
    public function createAttempt(int $tenantId, int $orderId, string $merchantOrderId, string $channelCode, int $amount, DateTimeInterface $expiresAt): PaymentData;

    public function findPendingForOrder(int $orderId): ?PaymentData;

    public function lockByMerchantOrderId(string $merchantOrderId): ?PaymentData;

    public function lockByPublicIdForCustomer(int $customerId, string $publicId): ?PaymentData;

    public function storeProviderResult(string $merchantOrderId, string $providerReference, string $paymentUrl): void;

    public function markUncertain(string $merchantOrderId): void;

    public function applyPaid(string $merchantOrderId, string $providerReference, ?int $feeAmount): bool;

    public function markTerminal(string $merchantOrderId, string $status): bool;

    public function flagMismatch(string $merchantOrderId): void;

    public function storeFee(string $merchantOrderId, int $feeAmount): void;

    public function recordEvent(?int $paymentId, string $fingerprint, string $providerStatus, ?string $providerReference, ?int $amount, bool $signatureOk): bool;

    public function expireOverdue(DateTimeInterface $now): int;

    /** @return list<array{code: string, label: string, category: string}> */
    public function activeChannels(): array;

    /** @return array{code: string, label: string, category: string, isActive: bool}|null */
    public function findChannel(string $channelCode): ?array;

    public function setChannelActive(string $channelCode, bool $active): void;

    /** @return array{items: list<array<string, mixed>>, meta: array<string, mixed>} */
    public function paginateForTenant(int $tenantId, int $perPage = 12): array;
}
