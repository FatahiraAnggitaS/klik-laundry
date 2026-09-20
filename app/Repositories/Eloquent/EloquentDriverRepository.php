<?php

namespace App\Repositories\Eloquent;

use App\DTOs\Dispatch\DriverData;
use App\DTOs\Dispatch\DriverInvitationData;
use App\DTOs\Dispatch\DriverSettingsData;
use App\Enums\DriverAvailability;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\DriverInvitation;
use App\Models\DriverProfile;
use App\Models\TenantDriverSetting;
use App\Models\User;
use App\Repositories\Contracts\DriverRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class EloquentDriverRepository implements DriverRepositoryInterface
{
    public function emailExists(string $email): bool
    {
        return User::query()->where('email', $email)->exists();
    }

    public function createInvitation(int $tenantId, int $actorId, string $email, string $phone, string $tokenHash, string $expiresAt): DriverInvitationData
    {
        DriverInvitation::query()->where('email', $email)->whereNotNull('active_email_key')->update([
            'active_email_key' => null,
            'revoked_at' => now(),
            'updated_at' => now(),
        ]);

        $invitation = DriverInvitation::query()->create([
            'public_id' => (string) Str::ulid(),
            'tenant_id' => $tenantId,
            'email' => $email,
            'phone' => $phone,
            'token_hash' => $tokenHash,
            'active_email_key' => 'driver:'.$email,
            'invited_by' => $actorId,
            'expires_at' => $expiresAt,
        ]);

        return $this->mapInvitation($invitation->load('tenant:id,name'));
    }

    public function lockInvitation(string $publicId, string $tokenHash): ?DriverInvitationData
    {
        $invitation = DriverInvitation::query()
            ->with('tenant:id,name')
            ->where('public_id', $publicId)
            ->where('token_hash', $tokenHash)
            ->lockForUpdate()
            ->first();

        return $invitation === null ? null : $this->mapInvitation($invitation);
    }

    public function findInvitationForTenant(int $tenantId, string $publicId): ?DriverInvitationData
    {
        $invitation = DriverInvitation::query()->with('tenant:id,name')->where('tenant_id', $tenantId)->where('public_id', $publicId)->first();

        return $invitation === null ? null : $this->mapInvitation($invitation);
    }

    public function markInvitationAccepted(int $invitationId): void
    {
        DriverInvitation::query()->whereKey($invitationId)->update(['accepted_at' => now(), 'active_email_key' => null, 'updated_at' => now()]);
    }

    public function revokeInvitation(int $invitationId): void
    {
        DriverInvitation::query()->whereKey($invitationId)->whereNull('accepted_at')->update(['revoked_at' => now(), 'active_email_key' => null, 'updated_at' => now()]);
    }

    public function createDriver(int $tenantId, string $name, string $email, string $phone, string $passwordHash): DriverData
    {
        $user = User::query()->create([
            'public_id' => (string) Str::ulid(),
            'tenant_id' => $tenantId,
            'role' => UserRole::Driver,
            'status' => UserStatus::Active,
            'auth_version' => 1,
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'password' => $passwordHash,
            'email_verified_at' => now(),
        ]);
        DriverProfile::query()->create(['user_id' => $user->id, 'tenant_id' => $tenantId, 'availability' => DriverAvailability::Unavailable]);

        return $this->mapDriver($user->load('driverProfile'));
    }

    public function findOwnedDriver(int $tenantId, string $publicId, bool $lock = false): ?DriverData
    {
        $query = User::query()->with('driverProfile')->where('tenant_id', $tenantId)->where('role', UserRole::Driver)->where('public_id', $publicId);
        $user = $lock ? $query->lockForUpdate()->first() : $query->first();

        return $user === null ? null : $this->mapDriver($user);
    }

    public function findDriver(int $driverId, bool $lock = false): ?DriverData
    {
        $query = User::query()->with('driverProfile')->whereKey($driverId)->where('role', UserRole::Driver);
        $user = $lock ? $query->lockForUpdate()->first() : $query->first();

        return $user === null ? null : $this->mapDriver($user);
    }

    public function updateAvailability(int $driverId, DriverAvailability $availability): DriverData
    {
        DriverProfile::query()->where('user_id', $driverId)->update(['availability' => $availability, 'updated_at' => now()]);

        return $this->mapDriver(User::query()->with('driverProfile')->findOrFail($driverId));
    }

    public function updateStatusAndRevokeSessions(int $driverId, UserStatus $status, string $reason): DriverData
    {
        User::query()->whereKey($driverId)->update([
            'status' => $status,
            'status_reason' => $reason,
            'suspended_at' => $status === UserStatus::Suspended ? now() : null,
            'remember_token' => null,
            'auth_version' => DB::raw('auth_version + 1'),
        ]);
        if (DB::getSchemaBuilder()->hasTable('sessions')) {
            DB::table('sessions')->where('user_id', $driverId)->delete();
        }

        return $this->mapDriver(User::query()->with('driverProfile')->findOrFail($driverId));
    }

    public function settings(int $tenantId): DriverSettingsData
    {
        $settings = TenantDriverSetting::query()->where('tenant_id', $tenantId)->first();

        return new DriverSettingsData($tenantId, $settings?->pickup_commission, $settings?->delivery_commission);
    }

    public function updateSettings(int $tenantId, int $actorId, int $pickupCommission, int $deliveryCommission): DriverSettingsData
    {
        $settings = TenantDriverSetting::query()->updateOrCreate(
            ['tenant_id' => $tenantId],
            ['pickup_commission' => $pickupCommission, 'delivery_commission' => $deliveryCommission, 'updated_by' => $actorId],
        );

        return new DriverSettingsData($tenantId, $settings->pickup_commission, $settings->delivery_commission);
    }

    public function paginateDrivers(int $tenantId, int $perPage = 12): array
    {
        $page = User::query()->with('driverProfile')->where('tenant_id', $tenantId)->where('role', UserRole::Driver)->latest('id')->paginate($perPage, ['*'], 'drivers')->withQueryString();

        return [
            'items' => $page->getCollection()->map(fn (User $user): array => $this->mapDriver($user)->toArray())->values()->all(),
            'meta' => $this->meta($page),
        ];
    }

    public function pendingInvitations(int $tenantId): array
    {
        return DriverInvitation::query()->with('tenant:id,name')->where('tenant_id', $tenantId)->whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at', '>', now())->latest('id')->limit(20)->get()->map(fn (DriverInvitation $invitation): array => $this->mapInvitation($invitation)->toArray())->all();
    }

    private function mapDriver(User $user): DriverData
    {
        $profile = $user->driverProfile;

        return new DriverData((int) $user->id, (int) $user->tenant_id, $user->public_id, $user->name, $user->email, (string) $user->phone, $user->status->value, $profile->availability->value);
    }

    private function mapInvitation(DriverInvitation $invitation): DriverInvitationData
    {
        return new DriverInvitationData(
            (int) $invitation->id,
            (int) $invitation->tenant_id,
            $invitation->public_id,
            $invitation->email,
            $invitation->phone,
            $invitation->tenant->name,
            $invitation->expires_at->toIso8601String(),
            $invitation->accepted_at?->toIso8601String(),
            $invitation->revoked_at?->toIso8601String(),
        );
    }

    /** @param LengthAwarePaginator<int, User> $page @return array{currentPage: int, lastPage: int, perPage: int, total: int} */
    private function meta(LengthAwarePaginator $page): array
    {
        return ['currentPage' => $page->currentPage(), 'lastPage' => $page->lastPage(), 'perPage' => $page->perPage(), 'total' => $page->total()];
    }
}
