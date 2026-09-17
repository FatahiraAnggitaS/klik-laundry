<?php

namespace App\Services\Outlets;

use App\Contracts\IdentityUser;
use App\Contracts\TransactionManagerInterface;
use App\DTOs\Audit\ActivityLogData;
use App\DTOs\Outlets\OutletInputData;
use App\Enums\ResourceStatus;
use App\Exceptions\Domain\DomainActionConflict;
use App\Exceptions\Domain\DomainRecordNotFound;
use App\Exceptions\Domain\PlatformSettingsUnavailable;
use App\Repositories\Contracts\ActivityLogRepositoryInterface;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Repositories\Contracts\OutletRepositoryInterface;
use App\Repositories\Contracts\PlatformSettingRepositoryInterface;
use App\Services\Tenancy\TenantOperationsGuard;

final readonly class ManageOutletService
{
    public function __construct(
        private TenantOperationsGuard $guard,
        private OutletRepositoryInterface $outlets,
        private OrderRepositoryInterface $orders,
        private PlatformSettingRepositoryInterface $settings,
        private ActivityLogRepositoryInterface $activityLogs,
        private TransactionManagerInterface $transactions,
    ) {}

    public function create(IdentityUser $actor, OutletInputData $data): string
    {
        $tenant = $this->guard->forMutation($actor);
        $this->assertRadius($data->serviceRadiusKm);

        return $this->transactions->run(function () use ($actor, $tenant, $data): string {
            $outlet = $this->outlets->create($tenant->id, $data);
            $this->audit($actor, $tenant->id, 'outlet.created', $outlet->publicId, [], ['status' => ResourceStatus::Draft->value]);

            return $outlet->publicId;
        });
    }

    public function update(IdentityUser $actor, string $publicId, OutletInputData $data): void
    {
        $tenant = $this->guard->forMutation($actor);
        $this->assertRadius($data->serviceRadiusKm);

        $this->transactions->run(function () use ($actor, $tenant, $publicId, $data): void {
            $outlet = $this->outlets->lockOwned($tenant->id, $publicId) ?? throw new DomainRecordNotFound;
            if ($outlet->status === ResourceStatus::Archived->value) {
                throw new DomainActionConflict('Archived outlet cannot be edited.', 'Outlet yang diarsipkan tidak dapat diubah.');
            }
            $this->outlets->update($outlet->id, $data);
            $this->audit($actor, $tenant->id, 'outlet.updated', $publicId, ['status' => $outlet->status], ['status' => $outlet->status]);
        });
    }

    public function delete(IdentityUser $actor, string $publicId): void
    {
        $tenant = $this->guard->forMutation($actor);
        $this->transactions->run(function () use ($actor, $tenant, $publicId): void {
            $outlet = $this->outlets->lockOwned($tenant->id, $publicId) ?? throw new DomainRecordNotFound;
            if ($outlet->status !== ResourceStatus::Draft->value) {
                throw new DomainActionConflict('Only draft outlet can be deleted.', 'Hanya outlet draft yang dapat dihapus.');
            }
            if ($this->orders->hasOrdersForOutlet($outlet->id)) {
                throw new DomainActionConflict('Referenced outlet cannot be deleted.', 'Outlet yang pernah dipakai order tidak dapat dihapus.');
            }
            $this->outlets->delete($outlet->id);
            $this->audit($actor, $tenant->id, 'outlet.deleted', $publicId, ['status' => $outlet->status], []);
        });
    }

    private function assertRadius(int $kilometers): void
    {
        $setting = $this->settings->findGlobal() ?? throw new PlatformSettingsUnavailable;
        if ($kilometers < 1 || $kilometers > $setting->maxServiceRadiusKm) {
            throw new DomainActionConflict('Service radius exceeds platform maximum.', "Radius layanan harus antara 1 dan {$setting->maxServiceRadiusKm} km.");
        }
    }

    /** @param array<string, bool|int|string|null> $before @param array<string, bool|int|string|null> $after */
    private function audit(IdentityUser $actor, int $tenantId, string $action, string $subjectId, array $before, array $after): void
    {
        $this->activityLogs->record(new ActivityLogData($tenantId, $actor->databaseId(), $action, 'outlet', $subjectId, before: $before, after: $after));
    }
}
