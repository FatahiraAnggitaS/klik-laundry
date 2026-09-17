<?php

namespace App\Services\Identity;

use App\Contracts\IdentityUser;
use App\DTOs\Identity\IdentitySummaryData;
use App\Enums\UserRole;

final class GetIdentitySummaryService
{
    public function handle(IdentityUser $user): IdentitySummaryData
    {
        return new IdentitySummaryData(
            publicId: $user->publicId(),
            name: $user->displayName(),
            email: $user->emailAddress(),
            phone: $user->phoneNumber(),
            role: $user->role()->value,
            roleLabel: $user->role()->label(),
            status: $user->status()->value,
            emailVerified: $user->hasVerifiedEmailAddress(),
            twoFactorRequired: in_array($user->role(), [UserRole::TenantOwner, UserRole::SuperUser], true),
            twoFactorEnabled: $user->hasConfirmedTwoFactorAuthentication(),
        );
    }
}
