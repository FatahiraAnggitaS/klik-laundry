<?php

namespace App\Repositories\Contracts;

use App\DTOs\Payments\PaymentData;
use DateTimeInterface;

interface PaymentRepositoryInterface
{
    public function createAttempt(int $tenantId, int $orderId, string $merchantOrderId, string $channelCode, int $amount, DateTimeInterface $expiresAt): PaymentData;

    public function findPendingForOrder(int $orderId): ?PaymentData;

    public function lockByMerchantOrderId(string $merchantOrderId): ?PaymentData;

    public function findByMerchantOrderId(string $merchantOrderId): ?PaymentData;

    public function findByPublicIdForSupport(string $publicId): ?PaymentData;

    public function claimInquiry(string $merchantOrderId, DateTimeInterface $availableBefore, DateTimeInterface $startedAt): bool;

    public function lockByPublicIdForCustomer(int $customerId, string $publicId): ?PaymentData;

    public function findByMerchantOrderIdForCustomer(int $customerId, string $merchantOrderId): ?PaymentData;

    public function storeProviderResult(string $merchantOrderId, string $providerReference, string $paymentUrl): void;

    public function markUncertain(string $merchantOrderId): void;

    public function applyPaid(string $merchantOrderId, string $providerReference, ?int $feeAmount): bool;

    public function markTerminal(string $merchantOrderId, string $status): bool;

    public function flagMismatch(string $merchantOrderId): void;

    public function storePaidFee(string $merchantOrderId, int $feeAmount): void;

    public function recordEvent(?int $paymentId, string $fingerprint, string $providerStatus, ?string $providerReference, ?int $amount, bool $signatureOk): bool;

    /** @return list<string> */
    public function overdueMerchantOrderIds(DateTimeInterface $now): array;

    /** @return list<array{code: string, label: string, category: string}> */
    public function activeChannels(): array;

    /** @return array{code: string, label: string, category: string, isActive: bool}|null */
    public function findChannel(string $channelCode): ?array;

    public function setChannelActive(string $channelCode, bool $active): void;

    /** @return list<array{code: string, label: string, category: string, isActive: bool, verifiedAt: ?string}> */
    public function channels(): array;

    /** @return array{items: list<array<string, mixed>>, meta: array<string, mixed>} */
    /** @param array{status?: string|null, reconciliation?: string|null, query?: string|null} $filters */
    public function paginateForTenant(int $tenantId, array $filters = [], int $perPage = 12): array;

    /** @param array{status?: string|null, reconciliation?: string|null, query?: string|null} $filters
     * @return array{items: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    public function paginateForSupport(array $filters, int $perPage = 12): array;
}
