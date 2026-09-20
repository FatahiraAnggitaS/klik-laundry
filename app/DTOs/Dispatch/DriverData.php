<?php

namespace App\DTOs\Dispatch;

final readonly class DriverData
{
    public function __construct(
        public int $id,
        public int $tenantId,
        public string $publicId,
        public string $name,
        public string $email,
        public string $phone,
        public string $status,
        public string $availability,
    ) {}

    /** @return array<string, int|string> */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
