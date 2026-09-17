<?php

namespace App\Http\Controllers\Catalog;

use App\Enums\ResourceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Outlets\TenantOperationRequest;
use App\Services\Catalog\ChangePackageStatusService;
use Illuminate\Http\RedirectResponse;

final class ChangePackageStatusController extends Controller
{
    public function __construct(private readonly ChangePackageStatusService $service) {}

    public function __invoke(TenantOperationRequest $request, string $package): RedirectResponse
    {
        $target = ResourceStatus::from((string) $request->route('target'));
        $this->service->handle($request->identity(), $package, $target);

        return back()->with('status', 'Status paket berhasil diperbarui.');
    }
}
