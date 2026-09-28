<?php

namespace App\Repositories\Contracts;

use App\DTOs\Privacy\ProofCleanupCandidateData;

interface ProofRetentionRepositoryInterface
{
    /** @return list<ProofCleanupCandidateData> */
    public function eligibleForDeletion(string $now, int $limit): array;

    public function markDeleted(ProofCleanupCandidateData $candidate, string $deletedAt): bool;

    public function markFailure(ProofCleanupCandidateData $candidate, string $failedAt, string $errorClass): void;

    /** @return array{eligible: int, failed: int, deleted: int} */
    public function summary(string $now): array;
}
