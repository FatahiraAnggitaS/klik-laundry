<?php

namespace App\Services\Payments;

use App\Contracts\IdentityUser;
use App\Contracts\TransactionManagerInterface;
use App\DTOs\Audit\ActivityLogData;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Exceptions\Domain\DomainRecordNotFound;
use App\Repositories\Contracts\ActivityLogRepositoryInterface;
use App\Repositories\Contracts\PlatformSettingRepositoryInterface;

final readonly class UpdatePaymentMaintenanceService
{
    public function __construct(
        private PlatformSettingRepositoryInterface $settings,
        private ActivityLogRepositoryInterface $activityLogs,
        private TransactionManagerInterface $transactions,
    ) {}

    public function handle(IdentityUser $actor, bool $enabled, string $reason): void
    {
        if ($actor->role() !== UserRole::SuperUser || $actor->status() !== UserStatus::Active) {
            throw new DomainRecordNotFound;
        }

        $this->transactions->run(function () use ($actor, $enabled, $reason): void {
            $current = $this->settings->findGlobal() ?? throw new DomainRecordNotFound;
            $this->settings->updatePaymentMaintenance($enabled, $actor->databaseId());
            $this->activityLogs->record(new ActivityLogData(
                tenantId: null,
                actorId: $actor->databaseId(),
                action: 'payment.maintenance_updated',
                subjectType: 'platform_setting',
                subjectId: 'global',
                reason: $reason,
                before: ['paymentMaintenanceEnabled' => $current->paymentMaintenanceEnabled],
                after: ['paymentMaintenanceEnabled' => $enabled],
            ));
        });
    }
}
