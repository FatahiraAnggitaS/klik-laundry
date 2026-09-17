<?php

namespace App\Services\Identity;

use App\Contracts\IdentityUser;
use App\Enums\UserStatus;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Support\Str;

final readonly class AuthenticateUserService
{
    public function __construct(
        private UserRepositoryInterface $users,
        private Hasher $hasher,
    ) {}

    public function handle(string $email, string $password): ?IdentityUser
    {
        $user = $this->users->findByEmail(Str::lower(trim($email)));

        if ($user === null || $user->status() !== UserStatus::Active) {
            return null;
        }

        return $this->hasher->check($password, $user->getAuthPassword()) ? $user : null;
    }
}
