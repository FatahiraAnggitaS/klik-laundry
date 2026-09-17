<?php

namespace App\Services\Catalog;

use App\Contracts\IdentityUser;
use App\Contracts\TransactionManagerInterface;
use App\DTOs\Audit\ActivityLogData;
use App\DTOs\Catalog\PackageInputData;
use App\Enums\PricingType;
use App\Enums\ResourceStatus;
use App\Exceptions\Domain\DomainActionConflict;
use App\Exceptions\Domain\DomainRecordNotFound;
use App\Repositories\Contracts\ActivityLogRepositoryInterface;
use App\Repositories\Contracts\PackageRepositoryInterface;
use App\Services\Tenancy\TenantOperationsGuard;

final readonly class ManagePackageService
{
    public function __construct(
        private TenantOperationsGuard $guard,
        private PackageRepositoryInterface $packages,
        private ActivityLogRepositoryInterface $activityLogs,
        private TransactionManagerInterface $transactions,
    ) {}

    public function create(IdentityUser $actor, PackageInputData $data): string
    {
        $tenant = $this->guard->forMutation($actor);
        $this->assertPricing($data);

        return $this->transactions->run(function () use ($actor, $tenant, $data): string {
            $package = $this->packages->create($tenant->id, $data);
            $this->audit($actor, $tenant->id, 'package.created', $package->publicId, [], ['status' => ResourceStatus::Draft->value]);

            return $package->publicId;
        });
    }

    public function update(IdentityUser $actor, string $publicId, PackageInputData $data): void
    {
        $tenant = $this->guard->forMutation($actor);
        $this->assertPricing($data);
        $this->transactions->run(function () use ($actor, $tenant, $publicId, $data): void {
            $package = $this->packages->lockOwned($tenant->id, $publicId) ?? throw new DomainRecordNotFound;
            if ($package->status === ResourceStatus::Archived->value) {
                throw new DomainActionConflict('Archived package cannot be edited.', 'Paket arsip tidak dapat diubah.');
            }
            $this->packages->update($package->id, $data);
            $this->audit($actor, $tenant->id, 'package.updated', $publicId, ['status' => $package->status], ['status' => $package->status]);
        });
    }

    public function delete(IdentityUser $actor, string $publicId): void
    {
        $tenant = $this->guard->forMutation($actor);
        $this->transactions->run(function () use ($actor, $tenant, $publicId): void {
            $package = $this->packages->lockOwned($tenant->id, $publicId) ?? throw new DomainRecordNotFound;
            if ($package->status !== ResourceStatus::Draft->value) {
                throw new DomainActionConflict('Only draft package can be deleted.', 'Hanya paket draft yang dapat dihapus.');
            }
            $this->packages->delete($package->id);
            $this->audit($actor, $tenant->id, 'package.deleted', $publicId, ['status' => $package->status], []);
        });
    }

    private function assertPricing(PackageInputData $data): void
    {
        $valid = $data->unitPrice > 0
            && $data->estimatedDurationMinutes > 0
            && ($data->pricingType === PricingType::Fixed
                ? $data->minimumQuantity !== null && $data->minimumQuantity >= 1 && $data->minimumWeightGrams === null
                : $data->minimumWeightGrams !== null && $data->minimumWeightGrams > 0 && $data->minimumQuantity === null && $data->unitPrice % 10 === 0);

        if (! $valid) {
            throw new DomainActionConflict('Invalid pricing-specific package fields.', 'Konfigurasi harga dan minimum paket tidak valid.');
        }
    }

    /** @param array<string, bool|int|string|null> $before @param array<string, bool|int|string|null> $after */
    private function audit(IdentityUser $actor, int $tenantId, string $action, string $subjectId, array $before, array $after): void
    {
        $this->activityLogs->record(new ActivityLogData($tenantId, $actor->databaseId(), $action, 'package', $subjectId, before: $before, after: $after));
    }
}
