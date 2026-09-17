<?php

namespace App\Repositories\Contracts;

use App\DTOs\Customers\CustomerAddressData;
use App\DTOs\Customers\CustomerAddressInputData;

interface CustomerAddressRepositoryInterface
{
    /** @return array{items: list<array<string, bool|float|string>>, meta: array{currentPage: int, lastPage: int, perPage: int, total: int}} */
    public function paginateOwned(int $customerId, int $perPage = 12): array;

    /** @return list<array<string, bool|float|string>> */
    public function listOwned(int $customerId): array;

    public function findOwned(int $customerId, string $publicId): ?CustomerAddressData;

    public function lockOwned(int $customerId, string $publicId): ?CustomerAddressData;

    public function create(int $customerId, CustomerAddressInputData $data): CustomerAddressData;

    public function update(int $addressId, CustomerAddressInputData $data): CustomerAddressData;

    public function setDefault(int $customerId, int $addressId): CustomerAddressData;

    public function delete(int $addressId): void;

    public function assignOldestAsDefault(int $customerId): void;
}
