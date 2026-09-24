<?php

namespace App\Services\Notifications;

use App\Contracts\IdentityUser;
use App\Contracts\TransactionManagerInterface;
use App\Exceptions\Domain\DomainRecordNotFound;
use App\Repositories\Contracts\NotificationRepositoryInterface;

final readonly class ManageNotificationsService
{
    public function __construct(
        private NotificationRepositoryInterface $notifications,
        private TransactionManagerInterface $transactions,
    ) {}

    /** @return array{items: list<array<string, mixed>>, meta: array{currentPage: int, lastPage: int, perPage: int, total: int}} */
    public function paginate(IdentityUser $actor): array
    {
        return $this->notifications->paginateForUser($actor->databaseId());
    }

    public function markRead(IdentityUser $actor, string $notificationId): void
    {
        $updated = $this->transactions->run(
            fn (): bool => $this->notifications->markRead($actor->databaseId(), $notificationId),
        );

        if (! $updated) {
            throw new DomainRecordNotFound;
        }
    }

    public function markAllRead(IdentityUser $actor): void
    {
        $this->transactions->run(function () use ($actor): void {
            $this->notifications->markAllRead($actor->databaseId());
        });
    }
}
