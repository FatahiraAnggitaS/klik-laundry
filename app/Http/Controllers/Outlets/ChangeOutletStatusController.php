<?php

namespace App\Http\Controllers\Outlets;

use App\Enums\ResourceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Outlets\TenantOperationRequest;
use App\Services\Outlets\ChangeOutletStatusService;
use Illuminate\Http\RedirectResponse;

final class ChangeOutletStatusController extends Controller
{
    public function __construct(private readonly ChangeOutletStatusService $service) {}

    public function __invoke(TenantOperationRequest $request, string $outlet): RedirectResponse
    {
        $target = ResourceStatus::from((string) $request->route('target'));
        $this->service->handle($request->identity(), $outlet, $target);

        return back()->with('status', 'Status outlet berhasil diperbarui.');
    }
}
