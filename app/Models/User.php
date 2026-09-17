<?php

namespace App\Models;

use App\Contracts\IdentityUser;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property UserRole $role
 * @property UserStatus $status
 * @property string $public_id
 * @property int|null $tenant_id
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property int $auth_version
 * @property string|null $two_factor_secret
 * @property Carbon|null $two_factor_confirmed_at
 */
#[Fillable([
    'public_id', 'tenant_id', 'role', 'status', 'status_reason', 'auth_version', 'role_slot',
    'name', 'email', 'phone', 'password', 'email_verified_at', 'suspended_at', 'closed_at',
])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
final class User extends Authenticatable implements IdentityUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return HasMany<CustomerAddress, $this> */
    public function customerAddresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class, 'customer_id');
    }

    public function databaseId(): int
    {
        return (int) $this->getKey();
    }

    public function publicId(): string
    {
        return $this->public_id;
    }

    public function displayName(): string
    {
        return $this->name;
    }

    public function emailAddress(): string
    {
        return $this->email;
    }

    public function phoneNumber(): ?string
    {
        return $this->phone;
    }

    public function tenantId(): ?int
    {
        return $this->tenant_id;
    }

    public function role(): UserRole
    {
        return $this->role;
    }

    public function status(): UserStatus
    {
        return $this->status;
    }

    public function authVersion(): int
    {
        return $this->auth_version;
    }

    public function hasConfirmedTwoFactorAuthentication(): bool
    {
        return $this->two_factor_confirmed_at !== null;
    }

    public function hasVerifiedEmailAddress(): bool
    {
        return $this->hasVerifiedEmail();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'status' => UserStatus::class,
            'auth_version' => 'integer',
            'two_factor_confirmed_at' => 'datetime',
            'suspended_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }
}
