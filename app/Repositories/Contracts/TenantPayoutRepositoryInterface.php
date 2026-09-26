<?php

namespace App\Repositories\Contracts;

use App\DTOs\Finance\TenantPayoutData;

interface TenantPayoutRepositoryInterface
{
    /** @return list<array{id: int, publicId: string, orderNumber: string, amount: int, feeAmount: int|null, status: string, reconciliation: string, paidAt: string|null, completedAt: string|null, hasActiveRefund: bool, claimed: bool}> */
    public function lockPaymentCandidates(int $tenantId, string $cutoffAt): array;

    /** @return list<array{id: int, publicId: string, amount: int, occurredAt: string, claimed: bool}> */
    public function lockAdjustmentCandidates(int $tenantId, string $cutoffAt): array;

    /** @return array{id: int, bankName: string, holderName: string, accountNumber: string, maskedAccountNumber: string}|null */
    public function lockVerifiedAccount(int $tenantId): ?array;

    /** @param list<array{id: int, amount: int, feeAmount: int}> $payments @param list<array{id: int, amount: int}> $adjustments */
    public function create(int $tenantId, int $accountId, string $cutoffAt, array $payments, array $adjustments, array $account, int $actorId): TenantPayoutData;

    public function lockForSupport(string $publicId): ?TenantPayoutData;

    public function findForSupport(string $publicId): ?TenantPayoutData;

    public function findForTenant(int $tenantId, string $publicId): ?TenantPayoutData;

    public function accountIsCurrentAndVerified(int $tenantId, int $accountId): bool;

    public function sourcesAreFinalizable(int $payoutId): bool;

    public function finalize(int $id, int $actorId, string $method, string $reference): TenantPayoutData;

    public function void(int $id, int $actorId, string $reason): TenantPayoutData;

    /** @return list<string> */
    public function voidPendingForAccountChange(int $tenantId, int $actorId): array;

    /** @return list<array<string, mixed>> */
    public function listForTenant(int $tenantId, int $limit = 20): array;

    /** @return list<array<string, mixed>> */
    public function listForSupport(int $limit = 30): array;

    public function hasOpenObligations(int $tenantId): bool;
}
