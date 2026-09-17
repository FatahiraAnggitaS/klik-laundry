<?php

namespace App\Http\Controllers\SuperUser;

use App\Enums\TenantOperationalStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\SuperUser\PlatformReasonRequest;
use App\Services\Tenancy\ChangeTenantOperationalStatusService;
use Illuminate\Http\RedirectResponse;

final class SuspendTenantController extends Controller
{
    public function __construct(private readonly ChangeTenantOperationalStatusService $service) {}

    public function __invoke(PlatformReasonRequest $request, string $tenant): RedirectResponse
    {
        $this->service->handle($request->identity(), $tenant, TenantOperationalStatus::Suspended, $request->validated('reason'));

        return back()->with('status', 'Tenant ditangguhkan.');
    }
}
