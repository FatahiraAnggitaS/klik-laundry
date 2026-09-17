<?php

namespace App\Http\Controllers\Outlets;

use App\Http\Controllers\Controller;
use App\Http\Requests\Outlets\OutletRequest;
use App\Http\Requests\Outlets\TenantOperationRequest;
use App\Services\Outlets\ManageOutletService;
use Illuminate\Http\RedirectResponse;

final class OutletController extends Controller
{
    public function __construct(private readonly ManageOutletService $service) {}

    public function store(OutletRequest $request): RedirectResponse
    {
        $this->service->create($request->identity(), $request->toDto());

        return back()->with('status', 'Outlet draft berhasil dibuat.');
    }

    public function update(OutletRequest $request, string $outlet): RedirectResponse
    {
        $this->service->update($request->identity(), $outlet, $request->toDto());

        return back()->with('status', 'Profil outlet berhasil diperbarui.');
    }

    public function destroy(TenantOperationRequest $request, string $outlet): RedirectResponse
    {
        $this->service->delete($request->identity(), $outlet);

        return back()->with('status', 'Outlet draft berhasil dihapus.');
    }
}
