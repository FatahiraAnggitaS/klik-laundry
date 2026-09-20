<?php

namespace App\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class DispatchLifecycleEvent implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $name,
        public readonly ?string $taskPublicId,
        public readonly string $orderPublicId,
        public readonly int $tenantId,
        public readonly ?int $driverId = null,
    ) {}
}
