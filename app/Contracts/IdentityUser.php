<?php

namespace App\Contracts;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\MustVerifyEmail;

interface IdentityUser extends Authenticatable, MustVerifyEmail
{
    public function databaseId(): int;

    public function publicId(): string;

    public function displayName(): string;

    public function emailAddress(): string;

    public function phoneNumber(): ?string;

    public function tenantId(): ?int;

    public function role(): UserRole;

    public function status(): UserStatus;

    public function authVersion(): int;

    public function hasConfirmedTwoFactorAuthentication(): bool;

    public function hasVerifiedEmailAddress(): bool;
}
