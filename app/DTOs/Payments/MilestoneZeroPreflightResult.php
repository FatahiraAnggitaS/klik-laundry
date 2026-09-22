<?php

namespace App\DTOs\Payments;

final readonly class MilestoneZeroPreflightResult
{
    public function __construct(
        public int $decisionCount,
        public string $reviewedAt,
        public string $stagingReference,
    ) {}
}
