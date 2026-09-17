<?php

namespace App\Http\Controllers\Outlets;

use App\Enums\SlotType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Outlets\OutletSlotRequest;
use App\Http\Requests\Outlets\TenantOperationRequest;
use App\Services\Outlets\ManageOutletScheduleService;
use Illuminate\Http\RedirectResponse;

final class OutletSlotController extends Controller
{
    public function __construct(private readonly ManageOutletScheduleService $service) {}

    public function store(OutletSlotRequest $request, string $outlet): RedirectResponse
    {
        $this->service->createSlot($request->identity(), $outlet, SlotType::from($request->string('type')->toString()), $request->integer('day_of_week'), $request->string('starts_at')->toString(), $request->string('ends_at')->toString());

        return back()->with('status', 'Slot berhasil ditambahkan.');
    }

    public function update(OutletSlotRequest $request, string $outlet, string $slot): RedirectResponse
    {
        $this->service->updateSlot($request->identity(), $outlet, $slot, SlotType::from($request->string('type')->toString()), $request->integer('day_of_week'), $request->string('starts_at')->toString(), $request->string('ends_at')->toString(), $request->boolean('active'));

        return back()->with('status', 'Slot berhasil diperbarui.');
    }

    public function destroy(TenantOperationRequest $request, string $outlet, string $slot): RedirectResponse
    {
        $this->service->deleteSlot($request->identity(), $outlet, $slot);

        return back()->with('status', 'Slot berhasil dihapus.');
    }
}
