<?php

namespace App\Services\Catalog;

use App\Contracts\IdentityUser;
use App\Contracts\TransactionManagerInterface;
use App\DTOs\Audit\ActivityLogData;
use App\Enums\ResourceStatus;
use App\Exceptions\Domain\DomainActionConflict;
use App\Exceptions\Domain\DomainRecordNotFound;
use App\Repositories\Contracts\ActivityLogRepositoryInterface;
use App\Repositories\Contracts\OutletRepositoryInterface;
use App\Repositories\Contracts\PackageRepositoryInterface;
use App\Services\Tenancy\TenantOperationsGuard;

final readonly class ChangePackageStatusService
{
    public function __construct(
        private TenantOperationsGuard $guard,
        private PackageRepositoryInterface $packages,
        private OutletRepositoryInterface $outlets,
        private ActivityLogRepositoryInterface $activityLogs,
        private TransactionManagerInterface $transactions,
    ) {}

    public function handle(IdentityUser $actor, string $publicId, ResourceStatus $target): void
    {
        $tenant = $this->guard->forMutation($actor);
        $this->transactions->run(function () use ($actor, $tenant, $publicId, $target): void {
            $package = $this->packages->lockOwned($tenant->id, $publicId) ?? throw new DomainRecordNotFound;
            $current = ResourceStatus::from($package->status);

            if ($current === ResourceStatus::Archived || $current === $target) {
                throw new DomainActionConflict('Invalid package status transition.', 'Transisi status paket tidak valid.');
            }

            if ($target === ResourceStatus::Active && $current !== ResourceStatus::Draft) {
                throw new DomainActionConflict('Only draft package can be activated.', 'Hanya paket draft yang dapat diaktifkan.');
            }

            if ($target === ResourceStatus::Draft) {
                if ($current !== ResourceStatus::Active) {
                    throw new DomainActionConflict('Only active package can be deactivated.', 'Hanya paket aktif yang dapat dinonaktifkan.');
                }
                if ($this->packages->countActiveForTenant($tenant->id) === 1 && $this->outlets->hasActiveForTenant($tenant->id)) {
                    throw new DomainActionConflict('Last active package is required by active outlets.', 'Nonaktifkan outlet sebelum menonaktifkan paket aktif terakhir.');
                }
            }

            if ($target === ResourceStatus::Archived && $current !== ResourceStatus::Draft) {
                throw new DomainActionConflict('Deactivate package before archiving.', 'Nonaktifkan paket sebelum mengarsipkannya.');
            }

            $this->packages->setStatus($package->id, $target);
            $this->activityLogs->record(new ActivityLogData(
                $tenant->id,
                $actor->databaseId(),
                "package.{$target->value}",
                'package',
                $publicId,
                before: ['status' => $current->value],
                after: ['status' => $target->value],
            ));
        });
    }
}
