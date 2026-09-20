<?php

namespace App\Services\Dispatch;

use App\Contracts\IdentityUser;
use App\Contracts\TransactionManagerInterface;
use App\DTOs\Audit\ActivityLogData;
use App\DTOs\Dispatch\DriverData;
use App\DTOs\Dispatch\DriverSettingsData;
use App\Enums\DriverAvailability;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Exceptions\Domain\DomainActionConflict;
use App\Exceptions\Domain\DomainRecordNotFound;
use App\Repositories\Contracts\ActivityLogRepositoryInterface;
use App\Repositories\Contracts\DispatchRepositoryInterface;
use App\Repositories\Contracts\DriverRepositoryInterface;
use App\Services\Tenancy\TenantOperationsGuard;

final readonly class ManageDriverService
{
    public function __construct(
        private DriverRepositoryInterface $drivers,
        private DispatchRepositoryInterface $dispatch,
        private TenantOperationsGuard $guard,
        private ActivityLogRepositoryInterface $activityLogs,
        private TransactionManagerInterface $transactions,
    ) {}

    public function updateSettings(IdentityUser $actor, int $pickup, int $delivery): DriverSettingsData
    {
        $tenant = $this->guard->forMutation($actor);

        return $this->transactions->run(function () use ($actor, $tenant, $pickup, $delivery): DriverSettingsData {
            $before = $this->drivers->settings($tenant->id);
            $settings = $this->drivers->updateSettings($tenant->id, $actor->databaseId(), $pickup, $delivery);
            $this->activityLogs->record(new ActivityLogData($tenant->id, $actor->databaseId(), 'driver.commission_settings_updated', 'tenant', $tenant->publicId, before: $before->toArray(), after: $settings->toArray()));

            return $settings;
        });
    }

    public function updateAvailability(IdentityUser $actor, DriverAvailability $availability): DriverData
    {
        $this->assertDriver($actor);

        return $this->transactions->run(function () use ($actor, $availability): DriverData {
            $driver = $this->drivers->findDriver($actor->databaseId(), true) ?? throw new DomainRecordNotFound;
            $updated = $this->drivers->updateAvailability($driver->id, $availability);
            $this->activityLogs->record(new ActivityLogData($driver->tenantId, $driver->id, 'driver.availability_updated', 'user', $driver->publicId, before: ['availability' => $driver->availability], after: ['availability' => $availability->value]));

            return $updated;
        });
    }

    public function deactivate(IdentityUser $actor, string $publicId, string $reason): DriverData
    {
        $tenant = $this->guard->forMutation($actor);

        return $this->transactions->run(function () use ($actor, $tenant, $publicId, $reason): DriverData {
            $driver = $this->drivers->findOwnedDriver($tenant->id, $publicId, true) ?? throw new DomainRecordNotFound;
            if ($driver->status !== UserStatus::Active->value || $this->dispatch->hasActiveTask($driver->id)) {
                throw new DomainActionConflict('Driver cannot be deactivated with an active task.', 'Driver harus menyelesaikan atau di-reassign dari task aktif.');
            }
            $this->dispatch->withdrawOffersForDriver($driver->id, $actor->databaseId(), $reason);
            $updated = $this->drivers->updateStatusAndRevokeSessions($driver->id, UserStatus::Suspended, $reason);
            $this->activityLogs->record(new ActivityLogData($tenant->id, $actor->databaseId(), 'driver.deactivated', 'user', $driver->publicId, $reason, ['status' => $driver->status], ['status' => $updated->status]));

            return $updated;
        });
    }

    public function reactivate(IdentityUser $actor, string $publicId, string $reason): DriverData
    {
        $tenant = $this->guard->forMutation($actor);

        return $this->transactions->run(function () use ($actor, $tenant, $publicId, $reason): DriverData {
            $driver = $this->drivers->findOwnedDriver($tenant->id, $publicId, true) ?? throw new DomainRecordNotFound;
            if ($driver->status !== UserStatus::Suspended->value) {
                throw new DomainActionConflict('Driver is not suspended.', 'Driver tidak sedang dinonaktifkan.');
            }
            $updated = $this->drivers->updateStatusAndRevokeSessions($driver->id, UserStatus::Active, $reason);
            $this->drivers->updateAvailability($driver->id, DriverAvailability::Unavailable);
            $this->activityLogs->record(new ActivityLogData($tenant->id, $actor->databaseId(), 'driver.reactivated', 'user', $driver->publicId, $reason, ['status' => $driver->status], ['status' => $updated->status]));

            return $updated;
        });
    }

    private function assertDriver(IdentityUser $actor): void
    {
        if ($actor->role() !== UserRole::Driver || $actor->status() !== UserStatus::Active || $actor->tenantId() === null) {
            throw new DomainRecordNotFound;
        }
    }
}
