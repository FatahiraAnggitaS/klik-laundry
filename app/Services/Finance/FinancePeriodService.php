<?php

namespace App\Services\Finance;

use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

final class FinancePeriodService
{
    /** @return array{from: string, to: string, fromDate: string, toDate: string} */
    public function normalize(?string $fromDate, ?string $toDate): array
    {
        $zone = 'Asia/Jakarta';
        $to = $toDate === null ? CarbonImmutable::now($zone) : CarbonImmutable::parse($toDate, $zone)->endOfDay();
        $from = $fromDate === null ? $to->subDays(29)->startOfDay() : CarbonImmutable::parse($fromDate, $zone)->startOfDay();
        if ($from->greaterThan($to) || $from->diffInDays($to) > 89) {
            throw ValidationException::withMessages(['from' => 'Rentang laporan maksimal 90 hari dan tanggal awal tidak boleh melewati tanggal akhir.']);
        }

        return ['from' => $from->utc()->toIso8601String(), 'to' => $to->utc()->toIso8601String(), 'fromDate' => $from->toDateString(), 'toDate' => $to->toDateString()];
    }

    public function cutoff(string $date): string
    {
        return CarbonImmutable::parse($date, 'Asia/Jakarta')->endOfDay()->utc()->toIso8601String();
    }
}
