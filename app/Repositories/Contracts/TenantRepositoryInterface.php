<?php

namespace App\Repositories\Contracts;

use App\DTOs\Tenancy\TenantApplicationData;
use App\DTOs\Tenancy\TenantRegistrationData;
use App\DTOs\Tenancy\TenantResubmissionData;
use App\Enums\TenantOnboardingStatus;
use App\Enums\TenantOperationalStatus;

interface TenantRepositoryInterface
{
    public function create(TenantRegistrationData $data): TenantApplicationData;

    public function findOwnedByTenantId(int $tenantId): ?TenantApplicationData;

    public function lockOwnedByTenantId(int $tenantId): ?TenantApplicationData;

    public function lockByPublicIdForReview(string $publicId): ?TenantApplicationData;

    /**
     * @return array{items: list<array<string, bool|int|string|null>>, meta: array{currentPage: int, lastPage: int, perPage: int, total: int}}
     */
    public function paginateForReview(int $perPage): array;

    public function setReviewDecision(
        int $tenantId,
        TenantOnboardingStatus $onboardingStatus,
        TenantOperationalStatus $operationalStatus,
        int $reviewerId,
        string $reason,
    ): TenantApplicationData;

    public function resubmit(int $tenantId, TenantResubmissionData $data): TenantApplicationData;

    public function setOperationalStatus(
        int $tenantId,
        TenantOperationalStatus $status,
        int $actorId,
        string $reason,
    ): TenantApplicationData;

    public function requestClosure(int $tenantId): TenantApplicationData;

    public function close(int $tenantId, int $actorId, string $reason): TenantApplicationData;

    public function setPayoutHold(int $tenantId, bool $hold, int $actorId, string $reason): TenantApplicationData;
}
