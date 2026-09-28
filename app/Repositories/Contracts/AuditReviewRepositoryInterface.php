<?php

namespace App\Repositories\Contracts;

interface AuditReviewRepositoryInterface
{
    /** @param array<string, string|null> $filters @return array{items: list<array<string, mixed>>, meta: array<string, int>} */
    public function paginate(array $filters, int $perPage = 30): array;
}
