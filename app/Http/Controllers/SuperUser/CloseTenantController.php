<?php

namespace App\Http\Controllers\SuperUser;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperUser\PlatformReasonRequest;
use App\Services\Tenancy\CloseTenantService;
use Illuminate\Http\RedirectResponse;

final class CloseTenantController extends Controller
{
    public function __construct(private readonly CloseTenantService $service) {}

    public function __invoke(PlatformReasonRequest $request, string $tenant): RedirectResponse
    {
        $this->service->handle($request->identity(), $tenant, $request->validated('reason'));

        return back()->with('status', 'Tenant ditutup dan seluruh sesi dicabut.');
    }
}
