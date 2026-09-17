<?php

namespace App\Repositories\Contracts;

use App\DTOs\Outlets\OutletData;
use App\DTOs\Outlets\OutletInputData;
use App\DTOs\Outlets\OutletSearchCriteria;
use App\Enums\ResourceStatus;
use App\Enums\SlotType;

interface OutletRepositoryInterface
{
    /** @return array{items: list<array<string, mixed>>, meta: array{currentPage: int, lastPage: int, perPage: int, total: int}} */
    public function paginateOwned(int $tenantId, int $perPage = 12, string $pageName = 'page'): array;

    public function findOwned(int $tenantId, string $publicId): ?OutletData;

    public function lockOwned(int $tenantId, string $publicId): ?OutletData;

    public function create(int $tenantId, OutletInputData $data): OutletData;

    public function update(int $outletId, OutletInputData $data): OutletData;

    public function syncInitialDraft(int $tenantId, OutletInputData $data): void;

    public function delete(int $outletId): void;

    /** @param list<array{day_of_week: int, opens_at: string, closes_at: string}> $hours */
    public function replaceOperatingHours(int $outletId, array $hours): OutletData;

    public function createSlot(int $outletId, SlotType $type, int $dayOfWeek, string $startsAt, string $endsAt): OutletData;

    public function updateSlot(int $outletId, string $slotPublicId, SlotType $type, int $dayOfWeek, string $startsAt, string $endsAt, bool $active): OutletData;

    public function deleteSlot(int $outletId, string $slotPublicId): OutletData;

    public function createBlackout(int $outletId, string $date, string $reason, int $actorId): OutletData;

    public function deleteBlackout(int $outletId, string $blackoutPublicId): OutletData;

    public function setStatus(int $outletId, ResourceStatus $status): OutletData;

    public function hasActiveForTenant(int $tenantId): bool;

    public function deactivateAllForTenant(int $tenantId): int;

    public function anyRadiusAbove(int $maximumRadiusMeters): bool;

    /** @return array{items: list<array<string, mixed>>, meta: array{currentPage: int, lastPage: int, perPage: int, total: int}} */
    public function search(OutletSearchCriteria $criteria): array;

    public function findDiscoverable(string $publicId, int $maximumRadiusMeters): ?OutletData;
}
