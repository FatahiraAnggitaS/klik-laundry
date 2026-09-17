<?php

namespace App\Services\Outlets;

use App\DTOs\Outlets\OutletData;
use Carbon\CarbonImmutable;

final class GetAvailableScheduleService
{
    /** @return list<array{slotPublicId: string, type: string, startsAt: string, endsAt: string}> */
    public function handle(OutletData $outlet, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now('Asia/Jakarta');
        $earliest = $now->addHours(2);
        $latest = $now->addDays(7);
        $blackouts = collect($outlet->blackouts)->pluck('date')->all();
        $available = [];

        for ($date = $now->startOfDay(); $date->lessThanOrEqualTo($latest); $date = $date->addDay()) {
            if (in_array($date->toDateString(), $blackouts, true)) {
                continue;
            }

            foreach ($outlet->slots as $slot) {
                if (! $slot['active'] || $slot['dayOfWeek'] !== $date->dayOfWeek) {
                    continue;
                }

                $startsAt = CarbonImmutable::parse($date->toDateString().' '.$slot['startsAt'], 'Asia/Jakarta');
                $endsAt = CarbonImmutable::parse($date->toDateString().' '.$slot['endsAt'], 'Asia/Jakarta');

                if ($startsAt->lessThan($earliest) || $startsAt->greaterThan($latest)) {
                    continue;
                }

                $available[] = [
                    'slotPublicId' => $slot['publicId'],
                    'type' => $slot['type'],
                    'startsAt' => $startsAt->toIso8601String(),
                    'endsAt' => $endsAt->toIso8601String(),
                ];
            }
        }

        return $available;
    }
}
