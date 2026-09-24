<?php

namespace App\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

final class DispatchLifecycleEvent implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $name,
        public readonly ?string $taskPublicId,
        public readonly string $orderPublicId,
        public readonly int $tenantId,
        public readonly ?int $driverId = null,
        public readonly ?int $customerId = null,
        ?string $eventId = null,
        ?string $occurredAt = null,
    ) {
        $this->eventId = $eventId ?? (string) Str::uuid();
        $this->occurredAt = $occurredAt ?? now()->utc()->toIso8601String();
    }

    public readonly string $eventId;

    public readonly string $occurredAt;
}
