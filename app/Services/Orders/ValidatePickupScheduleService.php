<?php

namespace App\Services\Orders;

use App\DTOs\Outlets\OutletData;
use App\Enums\SlotType;
use App\Exceptions\Domain\DomainActionConflict;
use App\Exceptions\Domain\DomainRecordNotFound;
use App\Repositories\Contracts\OutletRepositoryInterface;
use Carbon\CarbonImmutable;

final readonly class ValidatePickupScheduleService
{
    public function __construct(private OutletRepositoryInterface $outlets) {}

    /** @return array{id: int, startsAt: string, endsAt: string} */
    public function handle(OutletData $outlet, string $slotPublicId, string $date, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now('Asia/Jakarta');
        $slot = $this->outlets->findSlotForOutlet($outlet->id, $slotPublicId) ?? throw new DomainRecordNotFound;
        $startsAt = CarbonImmutable::parse("{$date} {$slot['startsAt']}", 'Asia/Jakarta');
        $endsAt = CarbonImmutable::parse("{$date} {$slot['endsAt']}", 'Asia/Jakarta');
        $valid = $slot['type'] === SlotType::Pickup->value
            && $slot['active']
            && $slot['dayOfWeek'] === $startsAt->dayOfWeek
            && $startsAt->greaterThanOrEqualTo($now->addHours(2))
            && $startsAt->lessThanOrEqualTo($now->addDays(7))
            && ! collect($outlet->blackouts)->contains('date', $date);

        if (! $valid) {
            throw new DomainActionConflict('Pickup schedule is unavailable.', 'Jadwal pickup tidak tersedia atau berada di luar batas pemesanan.');
        }

        return ['id' => $slot['id'], 'startsAt' => $startsAt->utc()->toIso8601String(), 'endsAt' => $endsAt->utc()->toIso8601String()];
    }
}
