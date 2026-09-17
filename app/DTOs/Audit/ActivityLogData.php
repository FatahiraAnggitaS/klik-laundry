<?php

namespace App\DTOs\Audit;

final readonly class ActivityLogData
{
    /**
     * @param  array<string, bool|int|string|null>  $before
     * @param  array<string, bool|int|string|null>  $after
     */
    public function __construct(
        public ?int $tenantId,
        public ?int $actorId,
        public string $action,
        public string $subjectType,
        public string $subjectId,
        public ?string $reason = null,
        public array $before = [],
        public array $after = [],
    ) {}
}
