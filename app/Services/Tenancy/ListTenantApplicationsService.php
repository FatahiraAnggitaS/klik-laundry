<?php

namespace App\Services\Tenancy;

use App\Contracts\IdentityUser;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Exceptions\Domain\DomainRecordNotFound;
use App\Repositories\Contracts\PayoutAccountRepositoryInterface;
use App\Repositories\Contracts\TenantRepositoryInterface;

final readonly class ListTenantApplicationsService
{
    public function __construct(
        private TenantRepositoryInterface $tenants,
        private PayoutAccountRepositoryInterface $payoutAccounts,
    ) {}

    /**
     * @return array{
     *     tenants: array{items: list<array<string, bool|int|string|null>>, meta: array{currentPage: int, lastPage: int, perPage: int, total: int}},
     *     payoutAccounts: list<array<string, int|string|null>>
     * }
     */
    public function handle(IdentityUser $actor): array
    {
        if ($actor->role() !== UserRole::SuperUser || $actor->status() !== UserStatus::Active) {
            throw new DomainRecordNotFound;
        }

        return [
            'tenants' => $this->tenants->paginateForReview(20),
            'payoutAccounts' => $this->payoutAccounts->pendingForReview(),
        ];
    }
}
