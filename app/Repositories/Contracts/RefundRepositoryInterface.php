<?php

namespace App\Repositories\Contracts;

use App\DTOs\Finance\RefundData;
use App\Enums\RefundStatus;

interface RefundRepositoryInterface
{
    /** @return array{id: int, tenantId: int, orderId: int, paymentId: int, paymentPublicId: string, orderNumber: string, paymentStatus: string, amount: int, completedAt: string|null}|null */
    public function lockSourceForTenant(int $tenantId, string $paymentPublicId): ?array;

    public function hasActiveOrCompletedForPayment(int $paymentId): bool;

    public function create(int $tenantId, int $orderId, int $paymentId, int $amount, string $reason, int $actorId): RefundData;

    public function lockForTenant(int $tenantId, string $publicId): ?RefundData;

    public function lockForSupport(string $publicId): ?RefundData;

    public function review(int $id, RefundStatus $status, int $actorId, string $reason): RefundData;

    public function complete(int $id, int $actorId, string $method, string $reference, string $reason): RefundData;

    /** @return array{items: list<array<string, int|string|null>>, meta: array<string, int>} */
    public function paginateForTenant(int $tenantId, int $perPage = 10): array;

    /** @return array{items: list<array<string, int|string|null>>, meta: array<string, int>} */
    public function paginateForSupport(int $perPage = 10): array;

    public function hasOpenObligations(int $tenantId): bool;
}
