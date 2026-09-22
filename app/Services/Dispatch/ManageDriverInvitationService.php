<?php

namespace App\Services\Dispatch;

use App\Contracts\IdentityUser;
use App\Contracts\TransactionManagerInterface;
use App\DTOs\Audit\ActivityLogData;
use App\DTOs\Dispatch\DriverData;
use App\DTOs\Dispatch\DriverInvitationData;
use App\Enums\TenantOnboardingStatus;
use App\Enums\TenantOperationalStatus;
use App\Enums\UserRole;
use App\Exceptions\Domain\DomainActionConflict;
use App\Exceptions\Domain\DomainRecordNotFound;
use App\Notifications\DriverInvitationNotification;
use App\Repositories\Contracts\ActivityLogRepositoryInterface;
use App\Repositories\Contracts\DriverRepositoryInterface;
use App\Repositories\Contracts\TenantRepositoryInterface;
use App\Services\Tenancy\TenantOperationsGuard;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

final readonly class ManageDriverInvitationService
{
    public function __construct(
        private DriverRepositoryInterface $drivers,
        private TenantRepositoryInterface $tenants,
        private TenantOperationsGuard $guard,
        private ActivityLogRepositoryInterface $activityLogs,
        private TransactionManagerInterface $transactions,
        private Hasher $hasher,
    ) {}

    public function invite(IdentityUser $actor, string $email, string $phone): DriverInvitationData
    {
        $tenant = $this->guard->forMutation($actor);
        $email = Str::lower(trim($email));
        if ($this->drivers->emailExists($email)) {
            throw new DomainActionConflict('Email already belongs to an account.', 'Email sudah digunakan oleh akun lain.');
        }
        $token = Str::random(64);
        $expiresAt = CarbonImmutable::now()->addHours(48);
        $invitation = $this->transactions->run(function () use ($actor, $tenant, $email, $phone, $token, $expiresAt): DriverInvitationData {
            $invitation = $this->drivers->createInvitation($tenant->id, $actor->databaseId(), $email, $phone, hash('sha256', $token), $expiresAt->toIso8601String());
            $this->activityLogs->record(new ActivityLogData($tenant->id, $actor->databaseId(), 'driver.invited', 'driver_invitation', $invitation->publicId, after: ['expiresInHours' => 48]));

            return $invitation;
        });
        $url = URL::route('driver.invitation.consume', ['invitation' => $invitation->publicId, 'token' => $token]);
        Notification::route('mail', $email)->notify(new DriverInvitationNotification($tenant->name, $url, $expiresAt->setTimezone('Asia/Jakarta')->format('d M Y H:i').' WIB'));

        return $invitation;
    }

    public function consume(string $publicId, string $token): DriverInvitationData
    {
        return $this->transactions->run(function () use ($publicId, $token): DriverInvitationData {
            $invitation = $this->drivers->lockInvitation($publicId, hash('sha256', $token)) ?? throw new DomainRecordNotFound;
            $this->assertUsable($invitation);

            return $invitation;
        });
    }

    public function accept(string $publicId, string $tokenHash, string $name, string $password): DriverData
    {
        return $this->transactions->run(function () use ($publicId, $tokenHash, $name, $password): DriverData {
            $invitation = $this->drivers->lockInvitation($publicId, $tokenHash) ?? throw new DomainRecordNotFound;
            $this->assertUsable($invitation);
            $tenant = $this->tenants->lockOwnedByTenantId($invitation->tenantId) ?? throw new DomainRecordNotFound;
            if ($tenant->onboardingStatus !== TenantOnboardingStatus::Approved->value
                || $tenant->operationalStatus !== TenantOperationalStatus::Active->value
                || $tenant->closureRequested) {
                throw new DomainActionConflict('Tenant is not eligible to accept Driver invitations.', 'Tenant tidak dapat menerima Driver baru saat ini.');
            }
            if ($this->drivers->emailExists($invitation->email)) {
                throw new DomainActionConflict('Invitation email already exists.', 'Email undangan sudah digunakan.');
            }
            $driver = $this->drivers->createDriver($invitation->tenantId, trim($name), $invitation->email, $invitation->phone, $this->hasher->make($password));
            $this->drivers->markInvitationAccepted($invitation->id);
            $this->activityLogs->record(new ActivityLogData($invitation->tenantId, $driver->id, 'driver.invitation_accepted', 'user', $driver->publicId, after: ['role' => UserRole::Driver->value, 'availability' => 'unavailable']));

            return $driver;
        });
    }

    public function revoke(IdentityUser $actor, string $publicId): void
    {
        $tenant = $this->guard->forMutation($actor);
        $this->transactions->run(function () use ($actor, $tenant, $publicId): void {
            $invitation = $this->drivers->findInvitationForTenant($tenant->id, $publicId) ?? throw new DomainRecordNotFound;
            if ($invitation->acceptedAt !== null || $invitation->revokedAt !== null) {
                throw new DomainActionConflict('Invitation is no longer revocable.', 'Undangan tidak lagi dapat dibatalkan.');
            }
            $this->drivers->revokeInvitation($invitation->id);
            $this->activityLogs->record(new ActivityLogData($tenant->id, $actor->databaseId(), 'driver.invitation_revoked', 'driver_invitation', $invitation->publicId));
        });
    }

    private function assertUsable(DriverInvitationData $invitation): void
    {
        if ($invitation->acceptedAt !== null || $invitation->revokedAt !== null || CarbonImmutable::parse($invitation->expiresAt)->isPast()) {
            throw new DomainActionConflict('Invitation is expired or already used.', 'Undangan kedaluwarsa atau sudah digunakan.');
        }
    }
}
