<?php

namespace App\Gateways\Identity;

use App\Contracts\IdentityUser;
use App\Models\User;
use Illuminate\Contracts\Hashing\Hasher;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;

final readonly class FortifySensitiveAuthenticationVerifier implements SensitiveAuthenticationVerifierInterface
{
    public function __construct(
        private Hasher $hasher,
        private TwoFactorAuthenticationProvider $twoFactorProvider,
    ) {}

    public function verify(IdentityUser $user, string $password, string $code): bool
    {
        if (! $user instanceof User
            || ! $this->hasher->check($password, $user->getAuthPassword())
            || $user->two_factor_secret === null
            || ! $user->hasConfirmedTwoFactorAuthentication()) {
            return false;
        }

        $secret = Fortify::currentEncrypter()->decrypt($user->two_factor_secret);

        return $this->twoFactorProvider->verify($secret, $code);
    }
}
