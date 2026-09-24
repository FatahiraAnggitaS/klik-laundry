<?php

namespace App\Repositories\Eloquent;

use App\Models\User;
use App\Repositories\Contracts\NotificationRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class EloquentNotificationRepository implements NotificationRepositoryInterface
{
    public function store(int $userId, string $dedupeKey, array $data): array
    {
        $id = (string) Str::uuid();
        $created = DB::table('notifications')->insertOrIgnore([
            'id' => $id, 'type' => 'domain_activity', 'notifiable_type' => User::class,
            'notifiable_id' => $userId, 'data' => json_encode($data, JSON_THROW_ON_ERROR),
            'dedupe_key' => $dedupeKey, 'created_at' => now(), 'updated_at' => now(),
        ]) === 1;

        if (! $created) {
            $id = (string) DB::table('notifications')->where('dedupe_key', $dedupeKey)->value('id');
        }

        return ['id' => $id, 'created' => $created];
    }

    public function paginateForUser(int $userId, int $perPage = 20): array
    {
        $page = DB::table('notifications')->where('notifiable_type', User::class)->where('notifiable_id', $userId)->latest('created_at')->paginate($perPage);

        return [
            'items' => collect($page->items())->map(fn (object $item): array => [
                'id' => $item->id, ...json_decode($item->data, true, flags: JSON_THROW_ON_ERROR),
                'readAt' => $item->read_at, 'createdAt' => $item->created_at,
            ])->all(),
            'meta' => ['currentPage' => $page->currentPage(), 'lastPage' => $page->lastPage(), 'perPage' => $page->perPage(), 'total' => $page->total()],
        ];
    }

    public function unreadCount(int $userId): int
    {
        return DB::table('notifications')->where('notifiable_type', User::class)->where('notifiable_id', $userId)->whereNull('read_at')->count();
    }

    public function markRead(int $userId, string $notificationId): bool
    {
        $query = DB::table('notifications')
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $userId)
            ->where('id', $notificationId);

        if (! $query->exists()) {
            return false;
        }

        $query->whereNull('read_at')->update(['read_at' => now(), 'updated_at' => now()]);

        return true;
    }

    public function markAllRead(int $userId): int
    {
        return DB::table('notifications')->where('notifiable_type', User::class)->where('notifiable_id', $userId)->whereNull('read_at')->update(['read_at' => now(), 'updated_at' => now()]);
    }
}
