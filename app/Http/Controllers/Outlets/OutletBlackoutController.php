<?php

namespace App\Http\Controllers\Outlets;

use App\Http\Controllers\Controller;
use App\Http\Requests\Outlets\BlackoutRequest;
use App\Http\Requests\Outlets\TenantOperationRequest;
use App\Services\Outlets\ManageOutletScheduleService;
use Illuminate\Http\RedirectResponse;

final class OutletBlackoutController extends Controller
{
    public function __construct(private readonly ManageOutletScheduleService $service) {}

    public function store(BlackoutRequest $request, string $outlet): RedirectResponse
    {
        $this->service->createBlackout($request->identity(), $outlet, $request->string('date')->toString(), $request->string('reason')->toString());

        return back()->with('status', 'Blackout berhasil ditambahkan.');
    }

    public function destroy(TenantOperationRequest $request, string $outlet, string $blackout): RedirectResponse
    {
        $this->service->deleteBlackout($request->identity(), $outlet, $blackout);

        return back()->with('status', 'Blackout berhasil dihapus.');
    }
}
