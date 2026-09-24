<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Foundation\Events\Dispatchable;

final class UserActivityBroadcast implements ShouldBroadcastNow, ShouldRescue
{
    use Dispatchable;

    /** @param array<string, string|null> $payload */
    public function __construct(public readonly string $userPublicId, public readonly array $payload) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('users.'.$this->userPublicId)];
    }

    public function broadcastAs(): string
    {
        return 'domain.activity';
    }

    /** @return array<string, string|null> */
    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
