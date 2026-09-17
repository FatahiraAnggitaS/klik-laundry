<?php

namespace App\Gateways\Identity;

use App\Contracts\IdentityUser;

interface SensitiveAuthenticationVerifierInterface
{
    public function verify(IdentityUser $user, string $password, string $code): bool;
}
