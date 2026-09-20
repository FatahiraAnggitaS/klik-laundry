<?php

namespace App\DTOs\Dispatch;

final readonly class WeightConfirmationData
{
    public function __construct(
        public string $publicId,
        public int $actualGrams,
        public int $minimumGrams,
        public int $billableGrams,
        public int $itemsSubtotal,
        public int $grandTotal,
        public bool $hasProof,
        public string $confirmedAt,
    ) {}

    /** @return array<string, bool|int|string> */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
