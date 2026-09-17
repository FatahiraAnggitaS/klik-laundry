<?php

namespace App\Repositories\Contracts;

use App\DTOs\Tenancy\PayoutAccountData;
use App\Enums\PayoutAccountStatus;

interface PayoutAccountRepositoryInterface
{
    public function findCurrentForTenant(int $tenantId): ?PayoutAccountData;

    public function lockCurrentForTenant(int $tenantId): ?PayoutAccountData;

    public function lockByPublicIdForReview(string $publicId): ?PayoutAccountData;

    /** @return list<array<string, int|string|null>> */
    public function pendingForReview(int $limit = 50): array;

    public function supersedeCurrent(int $tenantId): void;

    public function create(
        int $tenantId,
        int $submitterId,
        string $bankName,
        string $accountHolderName,
        string $accountNumber,
        string $maskedAccountNumber,
    ): PayoutAccountData;

    public function review(
        string $publicId,
        PayoutAccountStatus $status,
        int $reviewerId,
        string $reason,
    ): PayoutAccountData;
}
