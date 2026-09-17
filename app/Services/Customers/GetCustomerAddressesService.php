<?php

namespace App\Services\Customers;

use App\Contracts\IdentityUser;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Exceptions\Domain\DomainRecordNotFound;
use App\Repositories\Contracts\CustomerAddressRepositoryInterface;

final readonly class GetCustomerAddressesService
{
    public function __construct(private CustomerAddressRepositoryInterface $addresses) {}

    /** @return array{addresses: array<string, mixed>} */
    public function handle(IdentityUser $actor): array
    {
        if ($actor->role() !== UserRole::Customer || $actor->status() !== UserStatus::Active) {
            throw new DomainRecordNotFound;
        }

        return ['addresses' => $this->addresses->paginateOwned($actor->databaseId())];
    }
}
