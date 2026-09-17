<?php

namespace App\Policies;

use App\Contracts\IdentityUser;
use App\Enums\UserRole;
use App\Enums\UserStatus;

final class IdentityPolicy
{
    public function manageOwnTenant(IdentityUser $user): bool
    {
        return $user->role() === UserRole::TenantOwner && $user->status() === UserStatus::Active && $user->tenantId() !== null;
    }

    public function managePlatform(IdentityUser $user): bool
    {
        return $user->role() === UserRole::SuperUser && $user->status() === UserStatus::Active;
    }
}
