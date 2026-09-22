<?php

namespace App\Services\Payments;

use App\Contracts\IdentityUser;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Exceptions\Domain\DomainRecordNotFound;
use App\Repositories\Contracts\PaymentRepositoryInterface;

final readonly class GetTenantPaymentsService
{
    public function __construct(private PaymentRepositoryInterface $payments) {}

    /** @return array{items: list<array<string, mixed>>, meta: array<string, mixed>} */
    public function handle(IdentityUser $actor): array
    {
        if ($actor->role() !== UserRole::TenantOwner || $actor->status() !== UserStatus::Active || $actor->tenantId() === null) {
            throw new DomainRecordNotFound;
        }

        return $this->payments->paginateForTenant($actor->tenantId());
    }
}
