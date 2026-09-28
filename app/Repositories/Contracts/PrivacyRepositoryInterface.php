<?php

namespace App\Repositories\Contracts;

use App\DTOs\Privacy\AccountClosureReadinessData;
use App\DTOs\Privacy\OrderPrivacyData;
use App\DTOs\Privacy\PiiRevealGrantData;
use App\Enums\PiiAccessEvent;

interface PrivacyRepositoryInterface
{
    public function accountClosureReadiness(int $customerId): AccountClosureReadinessData;

    public function anonymizeCustomer(int $customerId, string $pseudonymousEmail, string $passwordHash): void;

    public function findOrderPrivacy(string $orderPublicId): ?OrderPrivacyData;

    public function createGrant(int $actorId, int $orderId, int $customerId, int $authVersion, string $sessionHash, string $reason, string $expiresAt): PiiRevealGrantData;

    public function findActiveGrant(int $actorId, int $orderId, int $authVersion, string $sessionHash, string $now): ?PiiRevealGrantData;

    public function revokeStaleGrants(int $actorId, int $authVersion, string $now): int;

    public function expireGrants(string $now, int $limit): int;

    public function recordAccess(?int $grantId, int $actorId, int $orderId, int $customerId, PiiAccessEvent $event): void;
}
