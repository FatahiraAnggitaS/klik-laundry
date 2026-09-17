<?php

namespace App\Http\Controllers\SuperUser;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperUser\PlatformReasonRequest;
use App\Services\Tenancy\SetTenantPayoutHoldService;
use Illuminate\Http\RedirectResponse;

final class ReleasePayoutHoldController extends Controller
{
    public function __construct(private readonly SetTenantPayoutHoldService $service) {}

    public function __invoke(PlatformReasonRequest $request, string $tenant): RedirectResponse
    {
        $this->service->handle($request->identity(), $tenant, false, $request->validated('reason'));

        return back()->with('status', 'Payout hold dilepas.');
    }
}
