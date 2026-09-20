<?php

namespace App\Repositories\Eloquent;

use App\Contracts\IdentityUser;
use App\DTOs\Identity\IdentityRegistrationData;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class EloquentUserRepository implements UserRepositoryInterface
{
    public function findByEmail(string $email): ?IdentityUser
    {
        return User::query()->where('email', $email)->first();
    }

    public function findByPublicId(string $publicId): ?IdentityUser
    {
        return User::query()->where('public_id', $publicId)->first();
    }

    public function lockByPublicId(string $publicId): ?IdentityUser
    {
        return User::query()->where('public_id', $publicId)->lockForUpdate()->first();
    }

    public function createCustomer(IdentityRegistrationData $data): IdentityUser
    {
        return $this->create($data, UserRole::Customer, null, null);
    }

    public function createTenantOwner(int $tenantId, IdentityRegistrationData $data): IdentityUser
    {
        return $this->create($data, UserRole::TenantOwner, $tenantId, "tenant:{$tenantId}");
    }

    public function createSuperUser(IdentityRegistrationData $data): IdentityUser
    {
        return $this->create($data, UserRole::SuperUser, null, 'super-user:primary');
    }

    public function superUserExists(): bool
    {
        return User::query()->where('role', UserRole::SuperUser)->exists();
    }

    public function updateProfile(IdentityUser $user, string $name, string $phone): IdentityUser
    {
        $model = User::query()->findOrFail($user->databaseId());
        $model->update(['name' => $name, 'phone' => $phone]);

        return $model->refresh();
    }

    public function updatePasswordHash(IdentityUser $user, string $passwordHash): void
    {
        User::query()->whereKey($user->databaseId())->update([
            'password' => $passwordHash,
            'remember_token' => null,
            'auth_version' => DB::raw('auth_version + 1'),
        ]);

        $this->deleteDatabaseSessions($user->databaseId());
    }

    public function changeStatusAndRevokeSessions(IdentityUser $user, UserStatus $status, string $reason): IdentityUser
    {
        $attributes = [
            'status' => $status,
            'status_reason' => $reason,
            'remember_token' => null,
            'auth_version' => DB::raw('auth_version + 1'),
            'suspended_at' => $status === UserStatus::Suspended ? now() : null,
            'closed_at' => $status === UserStatus::Closed ? now() : null,
        ];

        User::query()->whereKey($user->databaseId())->update($attributes);
        $this->deleteDatabaseSessions($user->databaseId());

        return User::query()->findOrFail($user->databaseId());
    }

    public function closeTenantUsersAndRevokeSessions(int $tenantId, string $reason): void
    {
        $userIds = User::query()->where('tenant_id', $tenantId)->pluck('id');

        User::query()->where('tenant_id', $tenantId)->update([
            'status' => UserStatus::Closed,
            'status_reason' => $reason,
            'closed_at' => now(),
            'remember_token' => null,
            'auth_version' => DB::raw('auth_version + 1'),
        ]);

        if ($userIds->isNotEmpty()) {
            DB::table('sessions')->whereIn('user_id', $userIds)->delete();
        }
    }

    private function create(
        IdentityRegistrationData $data,
        UserRole $role,
        ?int $tenantId,
        ?string $roleSlot,
    ): User {
        return User::query()->create([
            'public_id' => (string) Str::ulid(),
            'tenant_id' => $tenantId,
            'role' => $role,
            'status' => UserStatus::Active,
            'auth_version' => 1,
            'role_slot' => $roleSlot,
            'name' => $data->name,
            'email' => $data->email,
            'phone' => $data->phone,
            'password' => $data->passwordHash,
        ]);
    }

    private function deleteDatabaseSessions(int $userId): void
    {
        if (DB::getSchemaBuilder()->hasTable('sessions')) {
            DB::table('sessions')->where('user_id', $userId)->delete();
        }
    }
}
