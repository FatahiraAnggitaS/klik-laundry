<?php

namespace App\Repositories\Contracts;

use App\DTOs\Finance\DriverPayoutData;

interface DriverPayoutRepositoryInterface
{
    /** @return array{id: int, publicId: string, name: string, tenantId: int}|null */
    public function findDriverForTenant(int $tenantId, string $driverPublicId): ?array;

    /** @return list<array{id: int, publicId: string, amount: int, status: string, earnedAt: string, taskPublicId: string, orderNumber: string, taskType: string, claimed: bool}> */
    public function lockCommissionCandidates(int $tenantId, int $driverId, string $cutoffAt): array;

    /** @param list<array{id: int, amount: int}> $commissions */
    public function create(int $tenantId, int $driverId, string $cutoffAt, array $commissions, int $actorId): DriverPayoutData;

    public function lockForTenant(int $tenantId, string $publicId): ?DriverPayoutData;

    public function findForDriver(int $driverId, string $publicId): ?DriverPayoutData;

    public function sourcesAreFinalizable(int $payoutId): bool;

    public function finalize(int $id, int $actorId, string $method, ?string $reference, ?string $note): DriverPayoutData;

    public function void(int $id, int $actorId, string $reason): DriverPayoutData;

    /** @return list<array<string, mixed>> */
    public function listForTenant(int $tenantId, int $limit = 20): array;

    /** @return list<array<string, mixed>> */
    public function listForDriver(int $driverId, int $limit = 20): array;

    public function hasOpenObligations(int $tenantId): bool;
}
