<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\PackageRequest;
use App\Http\Requests\Outlets\TenantOperationRequest;
use App\Services\Catalog\ManagePackageService;
use Illuminate\Http\RedirectResponse;

final class PackageController extends Controller
{
    public function __construct(private readonly ManagePackageService $service) {}

    public function store(PackageRequest $request): RedirectResponse
    {
        $this->service->create($request->identity(), $request->toDto());

        return back()->with('status', 'Paket draft berhasil dibuat.');
    }

    public function update(PackageRequest $request, string $package): RedirectResponse
    {
        $this->service->update($request->identity(), $package, $request->toDto());

        return back()->with('status', 'Paket berhasil diperbarui.');
    }

    public function destroy(TenantOperationRequest $request, string $package): RedirectResponse
    {
        $this->service->delete($request->identity(), $package);

        return back()->with('status', 'Paket draft berhasil dihapus.');
    }
}
