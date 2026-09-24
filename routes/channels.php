<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel(
    'users.{publicId}',
    static fn (User $user, string $publicId): bool => hash_equals($user->public_id, $publicId),
    ['guards' => ['web']],
);
