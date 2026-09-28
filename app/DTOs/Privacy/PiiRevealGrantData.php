<?php

namespace App\DTOs\Privacy;

final readonly class PiiRevealGrantData
{
    public function __construct(
        public int $id,
        public string $publicId,
        public int $actorId,
        public int $orderId,
        public int $customerId,
        public string $expiresAt,
    ) {}

    /** @return array{publicId: string, expiresAt: string} */
    public function toArray(): array
    {
        return ['publicId' => $this->publicId, 'expiresAt' => $this->expiresAt];
    }
}
