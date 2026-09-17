<?php

namespace App\Http\Controllers\Tenancy;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenancy\TenantReasonRequest;
use App\Services\Tenancy\RequestTenantClosureService;
use Illuminate\Http\RedirectResponse;

final class RequestTenantClosureController extends Controller
{
    public function __construct(private readonly RequestTenantClosureService $service) {}

    public function __invoke(TenantReasonRequest $request): RedirectResponse
    {
        $this->service->handle($request->identity(), $request->validated('reason'));

        return back()->with('status', 'Permintaan penutupan Tenant dikirim.');
    }
}
