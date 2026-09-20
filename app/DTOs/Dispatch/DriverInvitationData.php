<?php

namespace App\DTOs\Dispatch;

final readonly class DriverInvitationData
{
    public function __construct(
        public int $id,
        public int $tenantId,
        public string $publicId,
        public string $email,
        public string $phone,
        public string $tenantName,
        public string $expiresAt,
        public ?string $acceptedAt,
        public ?string $revokedAt,
    ) {}

    /** @return array<string, int|string|null> */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
