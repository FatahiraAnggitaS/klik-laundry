<?php

namespace App\DTOs\Identity;

final readonly class IdentitySummaryData
{
    public function __construct(
        public string $publicId,
        public string $name,
        public string $email,
        public ?string $phone,
        public string $role,
        public string $roleLabel,
        public string $status,
        public bool $emailVerified,
        public bool $twoFactorRequired,
        public bool $twoFactorEnabled,
    ) {}

    /** @return array<string, bool|string|null> */
    public function toArray(): array
    {
        return [
            'publicId' => $this->publicId,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => $this->role,
            'roleLabel' => $this->roleLabel,
            'status' => $this->status,
            'emailVerified' => $this->emailVerified,
            'twoFactorRequired' => $this->twoFactorRequired,
            'twoFactorEnabled' => $this->twoFactorEnabled,
        ];
    }
}
