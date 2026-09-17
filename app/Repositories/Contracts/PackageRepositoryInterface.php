<?php

namespace App\Repositories\Contracts;

use App\DTOs\Catalog\PackageData;
use App\DTOs\Catalog\PackageInputData;
use App\Enums\ResourceStatus;

interface PackageRepositoryInterface
{
    /** @return array{items: list<array<string, int|string|null>>, meta: array{currentPage: int, lastPage: int, perPage: int, total: int}} */
    public function paginateOwned(int $tenantId, int $perPage = 12, string $pageName = 'page'): array;

    /** @return list<array<string, int|string|null>> */
    public function activeForTenant(int $tenantId): array;

    public function findOwned(int $tenantId, string $publicId): ?PackageData;

    public function lockOwned(int $tenantId, string $publicId): ?PackageData;

    public function create(int $tenantId, PackageInputData $data): PackageData;

    public function update(int $packageId, PackageInputData $data): PackageData;

    public function delete(int $packageId): void;

    public function setStatus(int $packageId, ResourceStatus $status): PackageData;

    public function countActiveForTenant(int $tenantId): int;
}
