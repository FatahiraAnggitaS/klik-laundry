<?php

namespace App\DTOs\Privacy;

final readonly class ProofCleanupCandidateData
{
    public function __construct(
        public string $source,
        public int $id,
        public string $disk,
        public string $key,
    ) {}
}
