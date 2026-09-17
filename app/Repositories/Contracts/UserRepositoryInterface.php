<?php

namespace App\Repositories\Contracts;

use App\Contracts\IdentityUser;
use App\DTOs\Identity\IdentityRegistrationData;
use App\Enums\UserStatus;

interface UserRepositoryInterface
{
    public function findByEmail(string $email): ?IdentityUser;

    public function findByPublicId(string $publicId): ?IdentityUser;

    public function lockByPublicId(string $publicId): ?IdentityUser;

    public function createCustomer(IdentityRegistrationData $data): IdentityUser;

    public function createTenantOwner(int $tenantId, IdentityRegistrationData $data): IdentityUser;

    public function createSuperUser(IdentityRegistrationData $data): IdentityUser;

    public function superUserExists(): bool;

    public function updateProfile(IdentityUser $user, string $name, string $phone): IdentityUser;

    public function updatePasswordHash(IdentityUser $user, string $passwordHash): void;

    public function changeStatusAndRevokeSessions(IdentityUser $user, UserStatus $status, string $reason): IdentityUser;

    public function closeTenantUsersAndRevokeSessions(int $tenantId, string $reason): void;
}
