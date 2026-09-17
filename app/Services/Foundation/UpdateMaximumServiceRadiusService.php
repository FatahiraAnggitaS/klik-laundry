<?php

namespace App\Services\Foundation;

use App\Contracts\IdentityUser;
use App\Contracts\TransactionManagerInterface;
use App\DTOs\Audit\ActivityLogData;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Exceptions\Domain\DomainActionConflict;
use App\Exceptions\Domain\DomainRecordNotFound;
use App\Exceptions\Domain\PlatformSettingsUnavailable;
use App\Repositories\Contracts\ActivityLogRepositoryInterface;
use App\Repositories\Contracts\OutletRepositoryInterface;
use App\Repositories\Contracts\PlatformSettingRepositoryInterface;

final readonly class UpdateMaximumServiceRadiusService
{
    public function __construct(
        private PlatformSettingRepositoryInterface $settings,
        private OutletRepositoryInterface $outlets,
        private ActivityLogRepositoryInterface $activityLogs,
        private TransactionManagerInterface $transactions,
    ) {}

    public function handle(IdentityUser $actor, int $kilometers, string $reason): void
    {
        if ($actor->role() !== UserRole::SuperUser || $actor->status() !== UserStatus::Active) {
            throw new DomainRecordNotFound;
        }

        $this->transactions->run(function () use ($actor, $kilometers, $reason): void {
            $current = $this->settings->findGlobal() ?? throw new PlatformSettingsUnavailable;
            if ($kilometers < 1 || $kilometers > 100) {
                throw new DomainActionConflict('Platform radius is outside safe bounds.', 'Batas radius harus antara 1 dan 100 km.');
            }
            if ($kilometers < $current->maxServiceRadiusKm && $this->outlets->anyRadiusAbove($kilometers * 1000)) {
                throw new DomainActionConflict('Configured outlet exceeds requested maximum.', 'Sesuaikan radius outlet yang melebihi batas baru terlebih dahulu.');
            }

            $this->settings->updateMaximumServiceRadius($kilometers, $actor->databaseId());
            $this->activityLogs->record(new ActivityLogData(
                tenantId: null,
                actorId: $actor->databaseId(),
                action: 'platform.maximum_service_radius_updated',
                subjectType: 'platform_setting',
                subjectId: 'global',
                reason: $reason,
                before: ['maxServiceRadiusKm' => $current->maxServiceRadiusKm],
                after: ['maxServiceRadiusKm' => $kilometers],
            ));
        });
    }
}
