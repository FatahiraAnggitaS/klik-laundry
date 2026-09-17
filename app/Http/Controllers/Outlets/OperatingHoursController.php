<?php

namespace App\Http\Controllers\Outlets;

use App\Http\Controllers\Controller;
use App\Http\Requests\Outlets\OperatingHoursRequest;
use App\Services\Outlets\ManageOutletScheduleService;
use Illuminate\Http\RedirectResponse;

final class OperatingHoursController extends Controller
{
    public function __construct(private readonly ManageOutletScheduleService $service) {}

    public function __invoke(OperatingHoursRequest $request, string $outlet): RedirectResponse
    {
        /** @var list<array{day_of_week: int, opens_at: string, closes_at: string}> $hours */
        $hours = $request->validated('hours');
        $this->service->replaceHours($request->identity(), $outlet, $hours);

        return back()->with('status', 'Jam operasional berhasil disimpan.');
    }
}
