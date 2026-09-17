<?php

namespace App\Repositories\Contracts;

use App\DTOs\Audit\ActivityLogData;

interface ActivityLogRepositoryInterface
{
    public function record(ActivityLogData $data): void;

    /** @return list<array{action: string, subjectType: string, subjectId: string, reason: string|null, createdAt: string}> */
    public function latestForTenant(int $tenantId, int $limit = 20): array;
}
