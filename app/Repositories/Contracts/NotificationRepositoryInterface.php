<?php

namespace App\Repositories\Contracts;

interface NotificationRepositoryInterface
{
    /** @param array<string, string|null> $data @return array{id: string, created: bool} */
    public function store(int $userId, string $dedupeKey, array $data): array;

    /** @return array{items: list<array<string, mixed>>, meta: array{currentPage: int, lastPage: int, perPage: int, total: int}} */
    public function paginateForUser(int $userId, int $perPage = 20): array;

    public function unreadCount(int $userId): int;

    public function markRead(int $userId, string $notificationId): bool;

    public function markAllRead(int $userId): int;
}
