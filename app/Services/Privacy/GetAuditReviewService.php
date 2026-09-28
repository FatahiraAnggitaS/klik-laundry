<?php

namespace App\Services\Privacy;

use App\Contracts\IdentityUser;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Exceptions\Domain\DomainRecordNotFound;
use App\Repositories\Contracts\AuditReviewRepositoryInterface;

final readonly class GetAuditReviewService
{
    public function __construct(private AuditReviewRepositoryInterface $audit) {}

    /** @param array<string, string|null> $filters @return array{items: list<array<string, mixed>>, meta: array<string, int>} */
    public function handle(IdentityUser $actor, array $filters): array
    {
        if ($actor->role() !== UserRole::SuperUser || $actor->status() !== UserStatus::Active) {
            throw new DomainRecordNotFound;
        }

        return $this->audit->paginate($filters);
    }
}
