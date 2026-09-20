<?php

namespace App\Repositories\Contracts;

use App\DTOs\Dispatch\DriverData;
use App\DTOs\Dispatch\DriverInvitationData;
use App\DTOs\Dispatch\DriverSettingsData;
use App\Enums\DriverAvailability;
use App\Enums\UserStatus;

interface DriverRepositoryInterface
{
    public function emailExists(string $email): bool;

    public function createInvitation(int $tenantId, int $actorId, string $email, string $phone, string $tokenHash, string $expiresAt): DriverInvitationData;

    public function lockInvitation(string $publicId, string $tokenHash): ?DriverInvitationData;

    public function findInvitationForTenant(int $tenantId, string $publicId): ?DriverInvitationData;

    public function markInvitationAccepted(int $invitationId): void;

    public function revokeInvitation(int $invitationId): void;

    public function createDriver(int $tenantId, string $name, string $email, string $phone, string $passwordHash): DriverData;

    public function findOwnedDriver(int $tenantId, string $publicId, bool $lock = false): ?DriverData;

    public function findDriver(int $driverId, bool $lock = false): ?DriverData;

    public function updateAvailability(int $driverId, DriverAvailability $availability): DriverData;

    public function updateStatusAndRevokeSessions(int $driverId, UserStatus $status, string $reason): DriverData;

    public function settings(int $tenantId): DriverSettingsData;

    public function updateSettings(int $tenantId, int $actorId, int $pickupCommission, int $deliveryCommission): DriverSettingsData;

    /** @return array{items: list<array<string, int|string>>, meta: array{currentPage: int, lastPage: int, perPage: int, total: int}} */
    public function paginateDrivers(int $tenantId, int $perPage = 12): array;

    /** @return list<array<string, int|string|null>> */
    public function pendingInvitations(int $tenantId): array;
}
