<?php

namespace App\Repositories\Contracts;

use App\DTOs\Dispatch\DriverOfferData;
use App\DTOs\Dispatch\DriverTaskData;
use App\DTOs\Dispatch\WeightConfirmationData;
use App\DTOs\Orders\OrderData;
use App\Enums\DriverTaskType;

interface DispatchRepositoryInterface
{
    public function createTask(OrderData $order, DriverTaskType $type, int $commissionAmount): DriverTaskData;

    public function findTaskForTenant(int $tenantId, string $publicId, bool $lock = false): ?DriverTaskData;

    public function findTaskForDriver(int $driverId, string $publicId, bool $lock = false): ?DriverTaskData;

    public function createOffer(int $taskId, int $driverId, string $expiresAt, ?int $actorId): DriverOfferData;

    public function findOfferForDriver(int $driverId, string $publicId, bool $lock = false): ?DriverOfferData;

    public function hasActiveTask(int $driverId, ?int $exceptTaskId = null): bool;

    public function acceptOffer(int $offerId, int $taskId, int $driverId): DriverTaskData;

    public function rejectOffer(int $offerId, int $taskId, int $driverId): DriverTaskData;

    public function startTask(int $taskId, int $driverId): DriverTaskData;

    public function completeTask(int $taskId, int $driverId, ?string $note, ?array $proof): DriverTaskData;

    public function findTaskForOrder(int $orderId, DriverTaskType $type, bool $lock = false): ?DriverTaskData;

    public function updateDeliverySchedule(int $orderId, string $startsAt, string $endsAt): void;

    public function resetForReassignment(int $taskId, int $actorId, string $reason): DriverTaskData;

    public function cancelForOrder(int $orderId, int $actorId, string $reason): void;

    public function withdrawOffersForDriver(int $driverId, ?int $actorId, string $reason): void;

    /** @return list<DriverOfferData> */
    public function expiredOffers(string $now): array;

    public function expireOffer(int $offerId, int $taskId): bool;

    /** @return array{items: list<array<string, mixed>>, meta: array{currentPage: int, lastPage: int, perPage: int, total: int}} */
    public function paginateForTenant(int $tenantId, int $perPage = 12): array;

    /** @return list<DriverOfferData> */
    public function offersForDriver(int $driverId): array;

    /** @return list<DriverTaskData> */
    public function tasksForDriver(int $driverId): array;

    public function currentWeight(int $orderId): ?WeightConfirmationData;

    public function confirmWeight(OrderData $order, int $actorId, int $actualGrams, int $billableGrams, int $itemsSubtotal, int $grandTotal, ?string $reason, ?array $proof): WeightConfirmationData;

    /** @return array{disk: string, key: string, expiresAt: string|null, revokedAt: string|null}|null */
    public function taskProof(int $tenantId, string $taskPublicId): ?array;

    /** @return array{disk: string, key: string, expiresAt: string|null, revokedAt: string|null}|null */
    public function driverTaskProof(int $driverId, string $taskPublicId): ?array;

    /** @return array{disk: string, key: string, expiresAt: string|null, revokedAt: string|null}|null */
    public function weightProof(int $tenantId, string $orderPublicId): ?array;

    public function revokeExpiredProofAccess(string $now): int;
}
