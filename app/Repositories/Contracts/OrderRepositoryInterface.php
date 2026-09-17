<?php

namespace App\Repositories\Contracts;

use App\DTOs\Orders\OrderCreationData;
use App\DTOs\Orders\OrderData;
use App\Enums\OrderIndicatorType;

interface OrderRepositoryInterface
{
    /** @return array{order: OrderData, created: bool} */
    public function createOrFind(OrderCreationData $data): array;

    public function findIdempotent(int $customerId, string $idempotencyKey): ?OrderData;

    public function findForCustomer(int $customerId, string $publicId): ?OrderData;

    public function findForTenant(int $tenantId, string $publicId): ?OrderData;

    public function lockByPublicId(string $publicId): ?OrderData;

    /** @param array{fulfillment_status?: string|null, payment_status?: string|null, query?: string|null} $filters @return array{items: list<array<string, mixed>>, meta: array{currentPage: int, lastPage: int, perPage: int, total: int}} */
    public function paginateForCustomer(int $customerId, array $filters, int $perPage = 12): array;

    /** @param array{fulfillment_status?: string|null, payment_status?: string|null, query?: string|null} $filters @return array{items: list<array<string, mixed>>, meta: array{currentPage: int, lastPage: int, perPage: int, total: int}} */
    public function paginateForTenant(int $tenantId, array $filters, int $perPage = 12): array;

    public function reschedulePickup(int $orderId, int $slotId, string $startsAt, string $endsAt, int $actorId, ?string $reason): OrderData;

    public function cancel(int $orderId, int $actorId, ?string $reason): OrderData;

    public function hasOrdersForOutlet(int $outletId): bool;

    public function hasOrdersForPackage(int $packageId): bool;

    public function hasOrdersForSlot(int $slotId): bool;

    public function hasScheduledOrderOnDate(int $outletId, string $date): bool;

    public function hasNonTerminalForTenant(int $tenantId): bool;

    /** @return list<OrderData> */
    public function monitoringCandidates(): array;

    /** @param array<string, int|string|null> $context */
    public function syncIndicator(int $orderId, OrderIndicatorType $type, bool $active, string $occurredAt, array $context = []): void;
}
