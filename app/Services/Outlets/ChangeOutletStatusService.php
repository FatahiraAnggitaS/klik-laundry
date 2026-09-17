<?php

namespace App\Services\Outlets;

use App\Contracts\IdentityUser;
use App\Contracts\TransactionManagerInterface;
use App\DTOs\Audit\ActivityLogData;
use App\Enums\ResourceStatus;
use App\Exceptions\Domain\DomainActionConflict;
use App\Exceptions\Domain\DomainRecordNotFound;
use App\Repositories\Contracts\ActivityLogRepositoryInterface;
use App\Repositories\Contracts\OutletRepositoryInterface;
use App\Services\Tenancy\TenantOperationsGuard;

final readonly class ChangeOutletStatusService
{
    public function __construct(
        private TenantOperationsGuard $guard,
        private OutletRepositoryInterface $outlets,
        private EvaluateOutletReadinessService $readiness,
        private ActivityLogRepositoryInterface $activityLogs,
        private TransactionManagerInterface $transactions,
    ) {}

    public function handle(IdentityUser $actor, string $publicId, ResourceStatus $target): void
    {
        $tenant = $this->guard->forMutation($actor);

        $this->transactions->run(function () use ($actor, $tenant, $publicId, $target): void {
            $outlet = $this->outlets->lockOwned($tenant->id, $publicId) ?? throw new DomainRecordNotFound;
            $current = ResourceStatus::from($outlet->status);

            if ($current === ResourceStatus::Archived || $current === $target) {
                throw new DomainActionConflict('Invalid outlet status transition.', 'Transisi status outlet tidak valid.');
            }

            if ($target === ResourceStatus::Active) {
                if ($current !== ResourceStatus::Draft) {
                    throw new DomainActionConflict('Only draft outlet can be activated.', 'Hanya outlet draft yang dapat diaktifkan.');
                }

                $blockers = $this->readiness->handle($tenant->id, $outlet);
                if ($blockers !== []) {
                    throw new DomainActionConflict('Outlet readiness requirements are incomplete.', $blockers[0]['label']);
                }
            } elseif ($target === ResourceStatus::Draft && $current !== ResourceStatus::Active) {
                throw new DomainActionConflict('Only active outlet can be deactivated.', 'Hanya outlet aktif yang dapat dinonaktifkan.');
            } elseif ($target === ResourceStatus::Archived && $current !== ResourceStatus::Draft) {
                throw new DomainActionConflict('Deactivate outlet before archiving.', 'Nonaktifkan outlet sebelum mengarsipkannya.');
            }

            $this->outlets->setStatus($outlet->id, $target);
            $this->activityLogs->record(new ActivityLogData(
                tenantId: $tenant->id,
                actorId: $actor->databaseId(),
                action: "outlet.{$target->value}",
                subjectType: 'outlet',
                subjectId: $publicId,
                before: ['status' => $current->value],
                after: ['status' => $target->value],
            ));
        });
    }
}
