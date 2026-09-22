<?php

namespace App\Services\Payments;

use App\Contracts\IdentityUser;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Exceptions\Domain\DomainRecordNotFound;
use App\Repositories\Contracts\PaymentRepositoryInterface;

final readonly class GetPaymentReconciliationService
{
    public function __construct(private PaymentRepositoryInterface $payments) {}

    /** @param array{status?: string|null, reconciliation?: string|null, query?: string|null} $filters
     * @return array{items: list<array<string, mixed>>, meta: array<string, mixed>, filters: array<string, string|null>}
     */
    public function handle(IdentityUser $actor, array $filters): array
    {
        if ($actor->role() !== UserRole::SuperUser || $actor->status() !== UserStatus::Active) {
            throw new DomainRecordNotFound;
        }

        return [...$this->payments->paginateForSupport($filters), 'filters' => $filters];
    }
}
