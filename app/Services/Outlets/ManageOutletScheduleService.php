<?php

namespace App\Services\Outlets;

use App\Contracts\IdentityUser;
use App\Contracts\TransactionManagerInterface;
use App\DTOs\Audit\ActivityLogData;
use App\DTOs\Outlets\OutletData;
use App\Enums\ResourceStatus;
use App\Enums\SlotType;
use App\Exceptions\Domain\DomainActionConflict;
use App\Exceptions\Domain\DomainRecordNotFound;
use App\Repositories\Contracts\ActivityLogRepositoryInterface;
use App\Repositories\Contracts\OutletRepositoryInterface;
use App\Services\Tenancy\TenantOperationsGuard;

final readonly class ManageOutletScheduleService
{
    public function __construct(
        private TenantOperationsGuard $guard,
        private OutletRepositoryInterface $outlets,
        private EvaluateOutletReadinessService $readiness,
        private ActivityLogRepositoryInterface $activityLogs,
        private TransactionManagerInterface $transactions,
    ) {}

    /** @param list<array{day_of_week: int, opens_at: string, closes_at: string}> $hours */
    public function replaceHours(IdentityUser $actor, string $outletPublicId, array $hours): void
    {
        $this->mutate($actor, $outletPublicId, 'outlet.hours_updated', function (OutletData $outlet) use ($hours): OutletData {
            foreach ($hours as $hour) {
                if ($hour['opens_at'] >= $hour['closes_at']) {
                    throw new DomainActionConflict('Operating hours are invalid.', 'Jam tutup harus setelah jam buka.');
                }
            }
            foreach ($outlet->slots as $slot) {
                if (! $this->slotFitsHours($slot['dayOfWeek'], $slot['startsAt'], $slot['endsAt'], $hours)) {
                    throw new DomainActionConflict('Existing slot falls outside new operating hours.', 'Jam baru tidak mencakup seluruh slot yang sudah ada.');
                }
            }

            return $this->outlets->replaceOperatingHours($outlet->id, $hours);
        });
    }

    public function createSlot(IdentityUser $actor, string $outletPublicId, SlotType $type, int $dayOfWeek, string $startsAt, string $endsAt): void
    {
        $this->mutate($actor, $outletPublicId, 'outlet.slot_created', function (OutletData $outlet) use ($type, $dayOfWeek, $startsAt, $endsAt): OutletData {
            $this->assertSlotFits($outlet, $dayOfWeek, $startsAt, $endsAt);
            if (collect($outlet->slots)->contains(fn (array $slot): bool => $slot['type'] === $type->value
                && $slot['dayOfWeek'] === $dayOfWeek
                && $slot['startsAt'] === $startsAt
                && $slot['endsAt'] === $endsAt)) {
                throw new DomainActionConflict('Duplicate outlet slot.', 'Slot yang sama sudah tersedia.');
            }

            return $this->outlets->createSlot($outlet->id, $type, $dayOfWeek, $startsAt, $endsAt);
        });
    }

    public function updateSlot(IdentityUser $actor, string $outletPublicId, string $slotPublicId, SlotType $type, int $dayOfWeek, string $startsAt, string $endsAt, bool $active): void
    {
        $this->mutate($actor, $outletPublicId, 'outlet.slot_updated', function (OutletData $outlet) use ($slotPublicId, $type, $dayOfWeek, $startsAt, $endsAt, $active): OutletData {
            if (! collect($outlet->slots)->contains('publicId', $slotPublicId)) {
                throw new DomainRecordNotFound;
            }
            $this->assertSlotFits($outlet, $dayOfWeek, $startsAt, $endsAt);
            if (collect($outlet->slots)->contains(fn (array $slot): bool => $slot['publicId'] !== $slotPublicId
                && $slot['type'] === $type->value
                && $slot['dayOfWeek'] === $dayOfWeek
                && $slot['startsAt'] === $startsAt
                && $slot['endsAt'] === $endsAt)) {
                throw new DomainActionConflict('Duplicate outlet slot.', 'Slot yang sama sudah tersedia.');
            }

            return $this->outlets->updateSlot($outlet->id, $slotPublicId, $type, $dayOfWeek, $startsAt, $endsAt, $active);
        });
    }

    public function deleteSlot(IdentityUser $actor, string $outletPublicId, string $slotPublicId): void
    {
        $this->mutate($actor, $outletPublicId, 'outlet.slot_deleted', function (OutletData $outlet) use ($slotPublicId): OutletData {
            if (! collect($outlet->slots)->contains('publicId', $slotPublicId)) {
                throw new DomainRecordNotFound;
            }

            return $this->outlets->deleteSlot($outlet->id, $slotPublicId);
        });
    }

    public function createBlackout(IdentityUser $actor, string $outletPublicId, string $date, string $reason): void
    {
        $this->mutate($actor, $outletPublicId, 'outlet.blackout_created', function (OutletData $outlet) use ($date, $reason, $actor): OutletData {
            if (collect($outlet->blackouts)->contains('date', $date)) {
                throw new DomainActionConflict('Duplicate outlet blackout.', 'Tanggal blackout sudah tersedia.');
            }

            return $this->outlets->createBlackout($outlet->id, $date, $reason, $actor->databaseId());
        }, $reason);
    }

    public function deleteBlackout(IdentityUser $actor, string $outletPublicId, string $blackoutPublicId): void
    {
        $this->mutate($actor, $outletPublicId, 'outlet.blackout_deleted', function (OutletData $outlet) use ($blackoutPublicId): OutletData {
            if (! collect($outlet->blackouts)->contains('publicId', $blackoutPublicId)) {
                throw new DomainRecordNotFound;
            }

            return $this->outlets->deleteBlackout($outlet->id, $blackoutPublicId);
        });
    }

    /** @param callable(OutletData): OutletData $callback */
    private function mutate(IdentityUser $actor, string $publicId, string $action, callable $callback, ?string $reason = null): void
    {
        $tenant = $this->guard->forMutation($actor);
        $this->transactions->run(function () use ($actor, $tenant, $publicId, $action, $callback, $reason): void {
            $outlet = $this->outlets->lockOwned($tenant->id, $publicId) ?? throw new DomainRecordNotFound;
            if ($outlet->status === ResourceStatus::Archived->value) {
                throw new DomainActionConflict('Archived outlet schedule cannot be changed.', 'Jadwal outlet arsip tidak dapat diubah.');
            }

            $updated = $callback($outlet);
            if ($outlet->status === ResourceStatus::Active->value) {
                $blockers = $this->readiness->handle($tenant->id, $updated);
                if ($blockers !== []) {
                    throw new DomainActionConflict('Mutation would make active outlet unready.', 'Nonaktifkan outlet sebelum menghapus konfigurasi readiness.');
                }
            }

            $this->activityLogs->record(new ActivityLogData($tenant->id, $actor->databaseId(), $action, 'outlet', $publicId, $reason));
        });
    }

    private function assertSlotFits(OutletData $outlet, int $dayOfWeek, string $startsAt, string $endsAt): void
    {
        $hours = array_map(fn (array $hour): array => [
            'day_of_week' => $hour['dayOfWeek'],
            'opens_at' => $hour['opensAt'],
            'closes_at' => $hour['closesAt'],
        ], $outlet->operatingHours);

        if (! $this->slotFitsHours($dayOfWeek, $startsAt, $endsAt, $hours)) {
            throw new DomainActionConflict('Slot falls outside operating hours.', 'Slot harus berada di dalam jam operasional pada hari yang sama.');
        }
    }

    /** @param list<array{day_of_week: int, opens_at: string, closes_at: string}> $hours */
    private function slotFitsHours(int $dayOfWeek, string $startsAt, string $endsAt, array $hours): bool
    {
        return collect($hours)->contains(fn (array $hour): bool => $hour['day_of_week'] === $dayOfWeek
            && $startsAt >= $hour['opens_at']
            && $endsAt <= $hour['closes_at']
            && $startsAt < $endsAt);
    }
}
