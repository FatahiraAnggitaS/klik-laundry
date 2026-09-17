<?php

namespace App\DTOs\Identity;

final readonly class IdentityRegistrationData
{
    public function __construct(
        public string $name,
        public string $email,
        public string $phone,
        public string $passwordHash,
    ) {}
}
